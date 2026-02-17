<?php

namespace MonkeysLegion\Logger\Tests\Unit;

use MonkeysLegion\Logger\Logger\NativeLogger;
use PHPUnit\Framework\TestCase;

class NativeLoggerTest extends TestCase
{
    /**
     * NativeLogger uses error_log() which writes to PHP's error log.
     * For testability we use message_type=3 (file) with a temp file.
     */
    private string $testLogPath;

    protected function setUp(): void
    {
        $this->testLogPath = sys_get_temp_dir() . '/native_test_' . uniqid() . '.log';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->testLogPath)) {
            unlink($this->testLogPath);
        }
    }

    public function testWritesToFile(): void
    {
        $logger = new NativeLogger('dev', [
            'message_type' => 3,
            'destination' => $this->testLogPath,
        ]);

        $logger->info('Native log message');

        $this->assertFileExists($this->testLogPath);
        $content = file_get_contents($this->testLogPath);
        $this->assertStringContainsString('Native log message', $content);
        $this->assertStringContainsString('INFO', $content);
    }

    public function testLogLevelFiltering(): void
    {
        $logger = new NativeLogger('dev', [
            'message_type' => 3,
            'destination' => $this->testLogPath,
            'level' => 'error',
        ]);

        $logger->debug('Should not appear');
        $logger->info('Should not appear');
        $logger->error('Should appear');

        $content = file_get_contents($this->testLogPath);
        $this->assertStringNotContainsString('Should not appear', $content);
        $this->assertStringContainsString('Should appear', $content);
    }

    public function testSmartLogByEnvironment(): void
    {
        $logger = new NativeLogger('production', [
            'message_type' => 3,
            'destination' => $this->testLogPath,
        ]);

        $logger->smartLog('Smart native log');

        $content = file_get_contents($this->testLogPath);
        $this->assertStringContainsString('INFO', $content);
        $this->assertStringContainsString('Smart native log', $content);
    }

    public function testAllLogLevels(): void
    {
        $logger = new NativeLogger('dev', [
            'message_type' => 3,
            'destination' => $this->testLogPath,
        ]);

        $logger->emergency('Emergency msg');
        $logger->alert('Alert msg');
        $logger->critical('Critical msg');
        $logger->error('Error msg');
        $logger->warning('Warning msg');
        $logger->notice('Notice msg');
        $logger->info('Info msg');
        $logger->debug('Debug msg');

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
        $logger = new NativeLogger('dev', [
            'message_type' => 3,
            'destination' => $this->testLogPath,
        ]);

        $logger->info('User login', ['user_id' => 123, 'ip' => '10.0.0.1']);

        $content = file_get_contents($this->testLogPath);
        $this->assertStringContainsString('"user_id":123', $content);
        $this->assertStringContainsString('"ip":"10.0.0.1"', $content);
    }

    public function testLogMethodAcceptsStringLevel(): void
    {
        $logger = new NativeLogger('dev', [
            'message_type' => 3,
            'destination' => $this->testLogPath,
        ]);

        $logger->log('warning', 'Generic log call');

        $content = file_get_contents($this->testLogPath);
        $this->assertStringContainsString('WARNING', $content);
        $this->assertStringContainsString('Generic log call', $content);
    }

    public function testDefaultMessageType(): void
    {
        // Without message_type, NativeLogger defaults to type 0 (PHP error log).
        // We can't easily verify where it goes, but we can verify it doesn't crash.
        $logger = new NativeLogger('dev', []);

        $logger->info('Default message type test');

        // If we get here without exception, the test passes
        $this->assertTrue(true);
    }

    public function testInvalidMessageTypeDefaultsToZero(): void
    {
        $logger = new NativeLogger('dev', [
            'message_type' => 'invalid',
        ]);

        // Should not crash — falls back to type 0
        $logger->info('Invalid type fallback');

        $this->assertTrue(true);
    }
}
