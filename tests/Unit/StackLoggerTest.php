<?php

namespace MonkeysLegion\Log\Tests\Unit;

use InvalidArgumentException;
use MonkeysLegion\Log\Factory\LoggerFactory;
use MonkeysLegion\Log\Logger\StackLogger;
use PHPUnit\Framework\TestCase;

class StackLoggerTest extends TestCase
{
    private string $testLogPath1;
    private string $testLogPath2;

    protected function setUp(): void
    {
        $this->testLogPath1 = sys_get_temp_dir() . '/stack_test1_' . uniqid() . '.log';
        $this->testLogPath2 = sys_get_temp_dir() . '/stack_test2_' . uniqid() . '.log';
    }

    protected function tearDown(): void
    {
        foreach ([$this->testLogPath1, $this->testLogPath2] as $path) {
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }

    public function testLogsToMultipleChannels(): void
    {
        $config = [
            'channels' => [
                'stack' => [
                    'driver' => 'stack',
                    'channels' => ['file1', 'file2'],
                ],
                'file1' => [
                    'driver' => 'file',
                    'path' => $this->testLogPath1,
                ],
                'file2' => [
                    'driver' => 'file',
                    'path' => $this->testLogPath2,
                ],
            ],
        ];

        $factory = new LoggerFactory($config);
        $logger = $factory->make('stack');

        $logger->info('Stack test message');

        $this->assertFileExists($this->testLogPath1);
        $this->assertFileExists($this->testLogPath2);

        $content1 = file_get_contents($this->testLogPath1);
        $content2 = file_get_contents($this->testLogPath2);

        $this->assertStringContainsString('Stack test message', $content1);
        $this->assertStringContainsString('Stack test message', $content2);
    }

    public function testPreventsStackRecursion(): void
    {
        $config = [
            'channels' => [
                'stack1' => [
                    'driver' => 'stack',
                    'channels' => ['file1'],
                ],
                'file1' => [
                    'driver' => 'file',
                    'path' => $this->testLogPath1,
                ],
            ],
        ];

        $factory = new LoggerFactory($config);
        $logger = $factory->make('stack1');

        $this->assertInstanceOf(StackLogger::class, $logger);

        // Should work without infinite recursion
        $logger->info('Test message');
        $this->assertFileExists($this->testLogPath1);
    }

    public function testRemovesDuplicateChannels(): void
    {
        $config = [
            'channels' => [
                'stack' => [
                    'driver' => 'stack',
                    'channels' => ['file1', 'file1', 'file1'],
                ],
                'file1' => [
                    'driver' => 'file',
                    'path' => $this->testLogPath1,
                ],
            ],
        ];

        $factory = new LoggerFactory($config);
        $logger = $factory->make('stack');

        $logger->info('Duplicate test');

        $content = file_get_contents($this->testLogPath1);
        $count = substr_count($content, 'Duplicate test');

        $this->assertEquals(1, $count);
    }

    public function testSkipsInvalidChannels(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Logger channel 'nonexistent' is not configured");

        $config = [
            'channels' => [
                'stack' => [
                    'driver' => 'stack',
                    'channels' => ['file1', 'nonexistent', 'file2'],
                ],
                'file1' => [
                    'driver' => 'file',
                    'path' => $this->testLogPath1,
                ],
                'file2' => [
                    'driver' => 'file',
                    'path' => $this->testLogPath2,
                ],
            ],
        ];

        $factory = new LoggerFactory($config);
        $logger = $factory->make('stack');

        $logger->warning('Skip invalid test');

        $this->assertFileExists($this->testLogPath1);
        $this->assertFileExists($this->testLogPath2);
    }

    public function testAllLogLevels(): void
    {
        $config = [
            'channels' => [
                'stack' => [
                    'driver' => 'stack',
                    'channels' => ['file1'],
                ],
                'file1' => [
                    'driver' => 'file',
                    'path' => $this->testLogPath1,
                ],
            ],
        ];

        $factory = new LoggerFactory($config);
        $logger = $factory->make('stack');

        $logger->emergency('Emergency');
        $logger->alert('Alert');
        $logger->critical('Critical');
        $logger->error('Error');
        $logger->warning('Warning');
        $logger->notice('Notice');
        $logger->info('Info');
        $logger->debug('Debug');
        $logger->smartLog('Smart');

        $content = file_get_contents($this->testLogPath1);

        $this->assertStringContainsString('EMERGENCY', $content);
        $this->assertStringContainsString('ALERT', $content);
        $this->assertStringContainsString('CRITICAL', $content);
        $this->assertStringContainsString('ERROR', $content);
        $this->assertStringContainsString('WARNING', $content);
        $this->assertStringContainsString('NOTICE', $content);
        $this->assertStringContainsString('INFO', $content);
        $this->assertStringContainsString('DEBUG', $content);
    }
}
