<?php

namespace MonkeysLegion\Logger\Tests\Unit;

use MonkeysLegion\Logger\Logger\FileLogger;
use PHPUnit\Framework\TestCase;

class FileLoggerTest extends TestCase
{
    private string $testLogPath;

    protected function setUp(): void
    {
        $this->testLogPath = sys_get_temp_dir() . '/file_logger_test_' . uniqid() . '.log';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->testLogPath)) {
            unlink($this->testLogPath);
        }
    }

    public function testWritesToFile(): void
    {
        $logger = new FileLogger('dev', ['path' => $this->testLogPath]);

        $logger->info('Test message');

        $this->assertFileExists($this->testLogPath);
        $content = file_get_contents($this->testLogPath);
        $this->assertStringContainsString('Test message', $content);
    }

    public function testAppendsToExistingFile(): void
    {
        $logger = new FileLogger('dev', ['path' => $this->testLogPath]);

        $logger->info('First message');
        $logger->info('Second message');

        $content = file_get_contents($this->testLogPath);
        $this->assertStringContainsString('First message', $content);
        $this->assertStringContainsString('Second message', $content);
    }

    public function testSmartLogEnvironmentAwareness(): void
    {
        $prodLogger = new FileLogger('production', ['path' => $this->testLogPath]);
        $prodLogger->smartLog('Production log');

        $content = file_get_contents($this->testLogPath);
        $this->assertStringContainsString('INFO', $content);

        unlink($this->testLogPath);

        $devLogger = new FileLogger('dev', ['path' => $this->testLogPath]);
        $devLogger->smartLog('Dev log');

        $content = file_get_contents($this->testLogPath);
        $this->assertStringContainsString('DEBUG', $content);
    }

    public function testAllLogLevels(): void
    {
        $logger = new FileLogger('dev', ['path' => $this->testLogPath]);

        $logger->emergency('Emergency');
        $logger->alert('Alert');
        $logger->critical('Critical');
        $logger->error('Error');
        $logger->warning('Warning');
        $logger->notice('Notice');
        $logger->info('Info');
        $logger->debug('Debug');

        $content = file_get_contents($this->testLogPath);
        $this->assertStringContainsString('EMERGENCY', $content);
        $this->assertStringContainsString('ALERT', $content);
        $this->assertStringContainsString('CRITICAL', $content);
        $this->assertStringContainsString('ERROR', $content);
        $this->assertStringContainsString('WARNING', $content);
        $this->assertStringContainsString('NOTICE', $content);
        $this->assertStringContainsString('INFO', $content);
        $this->assertStringContainsString('DEBUG', $content);
    }

    public function testContextLogging(): void
    {
        $logger = new FileLogger('dev', ['path' => $this->testLogPath]);

        $context = [
            'user_id' => 42,
            'action' => 'login',
            'ip' => '192.168.1.1',
        ];

        $logger->info('User action', $context);

        $content = file_get_contents($this->testLogPath);
        $this->assertStringContainsString('"user_id":42', $content);
        $this->assertStringContainsString('"action":"login"', $content);
    }
}
