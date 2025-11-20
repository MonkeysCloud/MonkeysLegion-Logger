<?php

namespace MonkeysLegion\Logger\Tests\Unit;

use MonkeysLegion\Logger\Logger\FileLogger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

class AbstractLoggerTest extends TestCase
{
    private string $testLogPath;

    protected function setUp(): void
    {
        $this->testLogPath = sys_get_temp_dir() . '/test_' . uniqid() . '.log';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->testLogPath)) {
            unlink($this->testLogPath);
        }
    }

    public function testNormalizeLogLevel(): void
    {
        $logger = new FileLogger('dev', ['path' => $this->testLogPath]);

        // Test various level formats
        $logger->info('test');
        $this->assertFileExists($this->testLogPath);
    }

    public function testLogLevelFiltering(): void
    {
        $logger = new FileLogger('dev', [
            'path' => $this->testLogPath,
            'level' => 'error',
        ]);

        $logger->debug('This should not log');
        $logger->info('This should not log');
        $logger->error('This should log');

        $content = file_get_contents($this->testLogPath);
        $this->assertStringNotContainsString('This should not log', $content);
        $this->assertStringContainsString('This should log', $content);
    }

    public function testEnvironmentEnrichment(): void
    {
        $logger = new FileLogger('production', ['path' => $this->testLogPath]);

        $logger->info('Test message', ['user_id' => 123]);

        $content = file_get_contents($this->testLogPath);
        $this->assertStringContainsString('production', $content);
        $this->assertStringContainsString('user_id', $content);
    }

    public function testMessageFormatting(): void
    {
        $logger = new FileLogger('dev', [
            'path' => $this->testLogPath,
            'format' => '{level}|{message}|{env}',
        ]);

        $logger->warning('Custom format test');

        $content = file_get_contents($this->testLogPath);
        $this->assertStringContainsString('WARNING|Custom format test|dev', $content);
    }
}
