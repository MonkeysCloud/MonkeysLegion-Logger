<?php

namespace MonkeysLegion\Logger\Tests\Integration;

use MonkeysLegion\Logger\Factory\LoggerFactory;
use MonkeysLegion\Logger\Formatter\JsonFormatter;
use MonkeysLegion\Logger\Logger\BufferLogger;
use MonkeysLegion\Logger\Logger\FileLogger;
use MonkeysLegion\Logger\Processor\IntrospectionProcessor;
use MonkeysLegion\Logger\Processor\MemoryUsageProcessor;
use MonkeysLegion\Logger\Processor\UidProcessor;
use PHPUnit\Framework\TestCase;

class ProcessorPipelineTest extends TestCase
{
    private string $testLogPath;

    protected function setUp(): void
    {
        $this->testLogPath = sys_get_temp_dir() . '/pipeline_test_' . uniqid() . '.log';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->testLogPath)) {
            unlink($this->testLogPath);
        }
    }

    public function testFullProcessorPipelineWithJsonFormatter(): void
    {
        $logger = new FileLogger('production', [
            'path' => $this->testLogPath,
            'channel' => 'worker',
        ]);

        $logger->setFormatter(new JsonFormatter());
        $logger->addProcessor(new UidProcessor(8));
        $logger->addProcessor(new MemoryUsageProcessor());

        $logger->error('Payment failed', [
            'order_id' => 'ORD-12345',
            'amount' => 99.99,
        ]);

        $content = file_get_contents($this->testLogPath);
        $decoded = json_decode(trim($content), true);

        $this->assertIsArray($decoded);
        $this->assertSame('ERROR', $decoded['level']);
        $this->assertSame('worker', $decoded['channel']);
        $this->assertSame('production', $decoded['env']);
        $this->assertSame('Payment failed', $decoded['message']);
        $this->assertSame('ORD-12345', $decoded['context']['order_id']);
        $this->assertSame(99.99, $decoded['context']['amount']);

        // Check processors enriched the extra field
        $this->assertArrayHasKey('extra', $decoded);
        $this->assertArrayHasKey('uid', $decoded['extra']);
        $this->assertSame(8, strlen($decoded['extra']['uid']));
        $this->assertArrayHasKey('memory_usage', $decoded['extra']);
        $this->assertArrayHasKey('memory_peak', $decoded['extra']);
    }

    public function testExceptionNormalizationWithJsonFormatter(): void
    {
        $logger = new FileLogger('production', [
            'path' => $this->testLogPath,
        ]);
        $logger->setFormatter(new JsonFormatter());

        $previous = new \InvalidArgumentException('Bad input', 422);
        $exception = new \RuntimeException('Controller crashed', 500, $previous);

        $logger->error('Request failed', ['exception' => $exception]);

        $content = file_get_contents($this->testLogPath);
        $decoded = json_decode(trim($content), true);

        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('exception', $decoded['context']);

        $exData = $decoded['context']['exception'];
        $this->assertSame('RuntimeException', $exData['class']);
        $this->assertSame('Controller crashed', $exData['message']);
        $this->assertSame(500, $exData['code']);
        $this->assertArrayHasKey('trace', $exData);

        // Previous exception
        $this->assertArrayHasKey('previous', $exData);
        $this->assertSame('InvalidArgumentException', $exData['previous']['class']);
        $this->assertSame('Bad input', $exData['previous']['message']);
    }

    public function testInterpolationWithLineFormatter(): void
    {
        $logger = new FileLogger('dev', [
            'path' => $this->testLogPath,
        ]);

        // LineFormatter is the default, which has interpolation
        $logger->info('User {user} performed {action}', [
            'user' => 'john',
            'action' => 'checkout',
        ]);

        $content = file_get_contents($this->testLogPath);
        $this->assertStringContainsString('User john performed checkout', $content);
    }

    public function testFactoryWiresFormatterAndProcessors(): void
    {
        $config = [
            'channels' => [
                'json_channel' => [
                    'driver' => 'file',
                    'path' => $this->testLogPath,
                    'formatter' => 'json',
                    'processors' => [
                        UidProcessor::class,
                        MemoryUsageProcessor::class,
                    ],
                ],
            ],
        ];

        $factory = new LoggerFactory($config);
        $logger = $factory->make('json_channel');

        $logger->info('Factory-wired log', ['request_id' => 'abc']);

        $content = file_get_contents($this->testLogPath);
        $decoded = json_decode(trim($content), true);

        $this->assertIsArray($decoded);
        $this->assertSame('INFO', $decoded['level']);
        $this->assertSame('Factory-wired log', $decoded['message']);
        $this->assertSame('abc', $decoded['context']['request_id']);
        $this->assertArrayHasKey('extra', $decoded);
        $this->assertArrayHasKey('uid', $decoded['extra']);
        $this->assertArrayHasKey('memory_usage', $decoded['extra']);
    }

    public function testFactoryCreatesBufferLogger(): void
    {
        $config = [
            'channels' => [
                'buffered' => [
                    'driver' => 'buffer',
                    'handler' => 'file',
                    'buffer_limit' => 5,
                    'flush_on_overflow' => true,
                ],
                'file' => [
                    'driver' => 'file',
                    'path' => $this->testLogPath,
                ],
            ],
        ];

        $factory = new LoggerFactory($config);
        $logger = $factory->make('buffered');

        $this->assertInstanceOf(BufferLogger::class, $logger);

        // Buffer 4 messages — should not write yet
        $logger->info('Msg 1');
        $logger->info('Msg 2');
        $logger->info('Msg 3');
        $logger->info('Msg 4');

        $this->assertFileDoesNotExist($this->testLogPath);

        // 5th message triggers auto-flush
        $logger->info('Msg 5');

        $content = file_get_contents($this->testLogPath);
        $this->assertStringContainsString('Msg 1', $content);
        $this->assertStringContainsString('Msg 5', $content);
    }

    public function testUidProcessorConsistencyAcrossRequests(): void
    {
        $uidProcessor = new UidProcessor(8);
        $logger = new FileLogger('dev', [
            'path' => $this->testLogPath,
        ]);
        $logger->setFormatter(new JsonFormatter());
        $logger->addProcessor($uidProcessor);

        $logger->info('First log');
        $logger->warning('Second log');

        $lines = array_filter(explode("\n", file_get_contents($this->testLogPath)));
        $this->assertCount(2, $lines);

        $first = json_decode($lines[0], true);
        $second = json_decode($lines[1], true);

        // Same UID across both records
        $this->assertSame(
            $first['extra']['uid'],
            $second['extra']['uid'],
            'UID should be consistent within the same request/process'
        );

        // Reset simulates a new worker iteration
        $uidProcessor->reset();

        $logger->info('Third log after reset');

        $lines = array_filter(explode("\n", file_get_contents($this->testLogPath)));
        $third = json_decode($lines[2], true);

        $this->assertNotSame(
            $first['extra']['uid'],
            $third['extra']['uid'],
            'UID should change after reset'
        );
    }

    public function testProductionStackWithProcessors(): void
    {
        $logPath2 = sys_get_temp_dir() . '/pipeline_stack_' . uniqid() . '.log';

        $config = [
            'default' => 'production',
            'channels' => [
                'production' => [
                    'driver' => 'stack',
                    'channels' => ['json_file'],
                ],
                'json_file' => [
                    'driver' => 'file',
                    'path' => $this->testLogPath,
                    'formatter' => 'json',
                    'processors' => [
                        UidProcessor::class,
                    ],
                ],
            ],
        ];

        $factory = new LoggerFactory($config, 'production');
        $logger = $factory->make();

        $logger->error('Stack with processors', [
            'exception' => new \RuntimeException('DB connection failed', 500),
        ]);

        $content = file_get_contents($this->testLogPath);
        $decoded = json_decode(trim($content), true);

        $this->assertIsArray($decoded);
        $this->assertSame('ERROR', $decoded['level']);
        $this->assertArrayHasKey('uid', $decoded['extra']);
        $this->assertArrayHasKey('exception', $decoded['context']);
        $this->assertSame('RuntimeException', $decoded['context']['exception']['class']);
    }
}
