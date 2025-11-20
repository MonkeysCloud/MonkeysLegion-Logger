<?php

namespace MonkeysLegion\Logger\Tests\Unit;

use InvalidArgumentException;
use MonkeysLegion\Logger\Factory\LoggerFactory;
use MonkeysLegion\Logger\Logger\ConsoleLogger;
use MonkeysLegion\Logger\Logger\FileLogger;
use MonkeysLegion\Logger\Logger\NullLogger;
use MonkeysLegion\Logger\Logger\StackLogger;
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
        $testDir = sys_get_temp_dir() . '/daily_test_' . uniqid();

        $config = [
            'channels' => [
                'daily' => [
                    'driver' => 'file',
                    'path' => $testDir . '/app.log',
                    'daily' => true,
                    'date_format' => 'Y-m-d',
                ],
            ],
        ];

        $factory = new LoggerFactory($config);
        $logger = $factory->make('daily');

        $this->assertInstanceOf(FileLogger::class, $logger);

        // Log something to create the file
        $logger->info('Test message');

        // Check that the file was created with date in filename
        $expectedDate = date('Y-m-d');
        $expectedFile = $testDir . '/app-' . $expectedDate . '.log';

        $this->assertFileExists($expectedFile);

        // Cleanup
        if (file_exists($expectedFile)) {
            unlink($expectedFile);
        }
        if (is_dir($testDir)) {
            rmdir($testDir);
        }
    }

    public function testDailyLoggerWithDifferentDateFormat(): void
    {
        $testDir = sys_get_temp_dir() . '/daily_format_test_' . uniqid();

        $config = [
            'channels' => [
                'daily' => [
                    'driver' => 'file',
                    'path' => $testDir . '/app.log',
                    'daily' => true,
                    'date_format' => 'Ymd',
                ],
            ],
        ];

        $factory = new LoggerFactory($config);
        $logger = $factory->make('daily');

        $logger->info('Test message');

        $expectedDate = date('Ymd');
        $expectedFile = $testDir . '/app-' . $expectedDate . '.log';

        $this->assertFileExists($expectedFile);

        // Cleanup
        if (file_exists($expectedFile)) {
            unlink($expectedFile);
        }
        if (is_dir($testDir)) {
            rmdir($testDir);
        }
    }

    public function testRegularFileLoggerDoesNotAddDate(): void
    {
        $testDir = sys_get_temp_dir() . '/regular_test_' . uniqid();
        $testPath = $testDir . '/app.log';

        $config = [
            'channels' => [
                'single' => [
                    'driver' => 'file',
                    'path' => $testPath,
                    'daily' => false,
                ],
            ],
        ];

        $factory = new LoggerFactory($config);
        $logger = $factory->make('single');

        $logger->info('Test message');

        // File should exist at exact path without date
        $this->assertFileExists($testPath);

        // File with date should NOT exist
        $dateFile = $testDir . '/app-' . date('Y-m-d') . '.log';
        $this->assertFileDoesNotExist($dateFile);

        // Cleanup
        if (file_exists($testPath)) {
            unlink($testPath);
        }
        if (is_dir($testDir)) {
            rmdir($testDir);
        }
    }

    public function testDailyLoggerWithDifferentExtensions(): void
    {
        $testDir = sys_get_temp_dir() . '/ext_test_' . uniqid();

        $testCases = [
            'errors.log' => 'errors-' . date('Y-m-d') . '.log',
            'debug.txt' => 'debug-' . date('Y-m-d') . '.txt',
            'app' => 'app-' . date('Y-m-d'),
        ];

        foreach ($testCases as $inputPath => $expectedFilename) {
            $config = [
                'channels' => [
                    'daily' => [
                        'driver' => 'file',
                        'path' => $testDir . '/' . $inputPath,
                        'daily' => true,
                    ],
                ],
            ];

            $factory = new LoggerFactory($config);
            $logger = $factory->make('daily');
            $logger->info('Test');

            $expectedFile = $testDir . '/' . $expectedFilename;
            $this->assertFileExists($expectedFile, "Failed for input: {$inputPath}");

            // Cleanup
            if (file_exists($expectedFile)) {
                unlink($expectedFile);
            }
        }

        if (is_dir($testDir)) {
            rmdir($testDir);
        }
    }
}
