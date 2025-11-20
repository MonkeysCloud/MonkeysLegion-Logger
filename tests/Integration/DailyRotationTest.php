<?php

namespace MonkeysLegion\Log\Tests\Integration;

use MonkeysLegion\Log\Factory\LoggerFactory;
use PHPUnit\Framework\TestCase;

class DailyRotationTest extends TestCase
{
    private string $logDir;

    protected function setUp(): void
    {
        $this->logDir = sys_get_temp_dir() . '/daily_rotation_' . uniqid();
        mkdir($this->logDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->recursiveRemoveDirectory($this->logDir);
    }

    public function testDailyRotationCreatesCorrectFilename(): void
    {
        $config = [
            'channels' => [
                'daily' => [
                    'driver' => 'file',
                    'path' => $this->logDir . '/app.log',
                    'daily' => true,
                    'date_format' => 'Y-m-d',
                ],
            ],
        ];

        $factory = new LoggerFactory($config, 'production');
        $logger = $factory->make('daily');

        // Log multiple messages
        for ($i = 0; $i < 10; $i++) {
            $logger->info("Message {$i}", ['iteration' => $i]);
        }

        // Verify the file was created with today's date
        $expectedFile = $this->logDir . '/app-' . date('Y-m-d') . '.log';
        $this->assertFileExists($expectedFile);

        // Verify content
        $content = file_get_contents($expectedFile);
        $this->assertStringContainsString('Message 0', $content);
        $this->assertStringContainsString('Message 9', $content);
    }

    public function testMultipleDailyLoggers(): void
    {
        $config = [
            'channels' => [
                'app_daily' => [
                    'driver' => 'file',
                    'path' => $this->logDir . '/app.log',
                    'daily' => true,
                ],
                'error_daily' => [
                    'driver' => 'file',
                    'path' => $this->logDir . '/errors.log',
                    'daily' => true,
                    'level' => 'error',
                ],
            ],
        ];

        $factory = new LoggerFactory($config);

        $appLogger = $factory->make('app_daily');
        $errorLogger = $factory->make('error_daily');

        $appLogger->info('Application started');
        $errorLogger->error('An error occurred');

        $expectedAppFile = $this->logDir . '/app-' . date('Y-m-d') . '.log';
        $expectedErrorFile = $this->logDir . '/errors-' . date('Y-m-d') . '.log';

        $this->assertFileExists($expectedAppFile);
        $this->assertFileExists($expectedErrorFile);

        $this->assertStringContainsString('Application started', file_get_contents($expectedAppFile));
        $this->assertStringContainsString('An error occurred', file_get_contents($expectedErrorFile));
    }

    public function testDailyLoggerInStack(): void
    {
        $config = [
            'channels' => [
                'stack' => [
                    'driver' => 'stack',
                    'channels' => ['daily', 'errors'],
                ],
                'daily' => [
                    'driver' => 'file',
                    'path' => $this->logDir . '/app.log',
                    'daily' => true,
                ],
                'errors' => [
                    'driver' => 'file',
                    'path' => $this->logDir . '/errors.log',
                    'level' => 'error',
                ],
            ],
        ];

        $factory = new LoggerFactory($config);
        $logger = $factory->make('stack');

        $logger->info('Info message');
        $logger->error('Error message');

        $expectedDailyFile = $this->logDir . '/app-' . date('Y-m-d') . '.log';
        $expectedErrorFile = $this->logDir . '/errors.log';

        $this->assertFileExists($expectedDailyFile);
        $this->assertFileExists($expectedErrorFile);

        $dailyContent = file_get_contents($expectedDailyFile);
        $this->assertStringContainsString('Info message', $dailyContent);
        $this->assertStringContainsString('Error message', $dailyContent);

        $errorContent = file_get_contents($expectedErrorFile);
        $this->assertStringNotContainsString('Info message', $errorContent);
        $this->assertStringContainsString('Error message', $errorContent);
    }

    public function testDailyLoggerWithCustomFormat(): void
    {
        $config = [
            'channels' => [
                'daily' => [
                    'driver' => 'file',
                    'path' => $this->logDir . '/custom.log',
                    'daily' => true,
                    'date_format' => 'Y_m_d',
                    'format' => '{timestamp}|{level}|{message}',
                ],
            ],
        ];

        $factory = new LoggerFactory($config);
        $logger = $factory->make('daily');

        $logger->warning('Custom format test');

        $expectedFile = $this->logDir . '/custom-' . date('Y_m_d') . '.log';
        $this->assertFileExists($expectedFile);

        $content = file_get_contents($expectedFile);
        $this->assertStringContainsString('|WARNING|Custom format test', $content);
    }

    public function testDailyAndRegularLoggersCoexist(): void
    {
        $config = [
            'channels' => [
                'regular' => [
                    'driver' => 'file',
                    'path' => $this->logDir . '/regular.log',
                ],
                'daily' => [
                    'driver' => 'file',
                    'path' => $this->logDir . '/daily.log',
                    'daily' => true,
                ],
            ],
        ];

        $factory = new LoggerFactory($config);

        $regularLogger = $factory->make('regular');
        $dailyLogger = $factory->make('daily');

        $regularLogger->info('Regular log');
        $dailyLogger->info('Daily log');

        $regularFile = $this->logDir . '/regular.log';
        $dailyFile = $this->logDir . '/daily-' . date('Y-m-d') . '.log';

        $this->assertFileExists($regularFile);
        $this->assertFileExists($dailyFile);

        $this->assertStringContainsString('Regular log', file_get_contents($regularFile));
        $this->assertStringContainsString('Daily log', file_get_contents($dailyFile));
    }

    private function recursiveRemoveDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->recursiveRemoveDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
