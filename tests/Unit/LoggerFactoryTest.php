<?php

namespace MonkeysLegion\Log\Tests\Unit;

use InvalidArgumentException;
use MonkeysLegion\Log\Factory\LoggerFactory;
use MonkeysLegion\Log\Logger\ConsoleLogger;
use MonkeysLegion\Log\Logger\FileLogger;
use MonkeysLegion\Log\Logger\NullLogger;
use MonkeysLegion\Log\Logger\StackLogger;
use PHPUnit\Framework\TestCase;

class LoggerFactoryTest extends TestCase
{
    private string $testLogPath;

    protected function setUp(): void
    {
        $this->testLogPath = sys_get_temp_dir() . '/factory_test_' . uniqid() . '.log';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->testLogPath)) {
            unlink($this->testLogPath);
        }
    }

    public function testCreatesFileLogger(): void
    {
        $config = [
            'channels' => [
                'file' => [
                    'driver' => 'file',
                    'path' => $this->testLogPath,
                    'level' => 'debug',
                ],
            ],
        ];

        $factory = new LoggerFactory($config);
        $logger = $factory->make('file');

        $this->assertInstanceOf(FileLogger::class, $logger);
    }

    public function testCreatesConsoleLogger(): void
    {
        $config = [
            'channels' => [
                'console' => [
                    'driver' => 'console',
                    'colorize' => true,
                ],
            ],
        ];

        $factory = new LoggerFactory($config);
        $logger = $factory->make('console');

        $this->assertInstanceOf(ConsoleLogger::class, $logger);
    }

    public function testCreatesNullLogger(): void
    {
        $config = [
            'channels' => [
                'null' => [
                    'driver' => 'null',
                ],
            ],
        ];

        $factory = new LoggerFactory($config);
        $logger = $factory->make('null');

        $this->assertInstanceOf(NullLogger::class, $logger);
    }

    public function testCreatesStackLogger(): void
    {
        $config = [
            'channels' => [
                'stack' => [
                    'driver' => 'stack',
                    'channels' => ['console', 'null'],
                ],
                'console' => ['driver' => 'console'],
                'null' => ['driver' => 'null'],
            ],
        ];

        $factory = new LoggerFactory($config);
        $logger = $factory->make('stack');

        $this->assertInstanceOf(StackLogger::class, $logger);
    }

    public function testUsesDefaultChannel(): void
    {
        $config = [
            'default' => 'null',
            'channels' => [
                'null' => ['driver' => 'null'],
            ],
        ];

        $factory = new LoggerFactory($config);
        $logger = $factory->make();

        $this->assertInstanceOf(NullLogger::class, $logger);
    }

    public function testThrowsExceptionForInvalidChannel(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Logger channel 'nonexistent' is not configured");

        $factory = new LoggerFactory(['channels' => []]);
        $factory->make('nonexistent');
    }

    public function testThrowsExceptionForInvalidDriver(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Unsupported logger driver: invalid");

        $config = [
            'channels' => [
                'test' => ['driver' => 'invalid'],
            ],
        ];

        $factory = new LoggerFactory($config);
        $factory->make('test');
    }

    public function testDetectsCircularDependency(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Circular dependency detected");

        $config = [
            'channels' => [
                'stack1' => [
                    'driver' => 'stack',
                    'channels' => ['stack2'],
                ],
                'stack2' => [
                    'driver' => 'stack',
                    'channels' => ['stack3'],
                ],
                'stack3' => [
                    'driver' => 'stack',
                    'channels' => ['stack1'],
                ],
            ],
        ];

        $factory = new LoggerFactory($config);
        $logger = $factory->make('stack1');

        // Try to actually use the logger to trigger resolution
        $logger->info('test');
    }

    public function testDatePlaceholderReplacement(): void
    {
        $config = [
            'channels' => [
                'daily' => [
                    'driver' => 'file',
                    'path' => sys_get_temp_dir() . '/app-{date}.log',
                    'date_format' => 'Y-m-d',
                ],
            ],
        ];

        $factory = new LoggerFactory($config);
        $logger = $factory->make('daily');

        $this->assertInstanceOf(FileLogger::class, $logger);
    }

    public function testCreatesDirectoryIfNotExists(): void
    {
        $testDir = sys_get_temp_dir() . '/log_test_' . uniqid();
        $testPath = $testDir . '/app.log';

        $config = [
            'channels' => [
                'file' => [
                    'driver' => 'file',
                    'path' => $testPath,
                ],
            ],
        ];

        $factory = new LoggerFactory($config);
        $logger = $factory->make('file');

        $this->assertDirectoryExists($testDir);

        // Cleanup
        if (file_exists($testPath)) {
            unlink($testPath);
        }
        if (is_dir($testDir)) {
            rmdir($testDir);
        }
    }
}
