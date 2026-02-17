<?php

namespace MonkeysLegion\Logger\Tests\Unit;

use MonkeysLegion\Logger\Logger\FileLogger;
use PHPUnit\Framework\TestCase;

class ExceptionFormattingTest extends TestCase
{
    private string $testLogPath;

    protected function setUp(): void
    {
        $this->testLogPath = sys_get_temp_dir() . '/exception_test_' . uniqid() . '.log';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->testLogPath)) {
            unlink($this->testLogPath);
        }
    }

    public function testExceptionInContextIsFormatted(): void
    {
        $logger = new FileLogger('dev', ['path' => $this->testLogPath]);

        $exception = new \RuntimeException('Controller failed', 500);

        $logger->error('Request failed', ['exception' => $exception]);

        $content = file_get_contents($this->testLogPath);
        $this->assertStringContainsString('Controller failed', $content);
        $this->assertStringContainsString('RuntimeException', $content);
        $this->assertStringContainsString('500', $content);
    }

    public function testExceptionWithFileAndLine(): void
    {
        $logger = new FileLogger('dev', ['path' => $this->testLogPath]);

        $exception = new \RuntimeException('Test error');

        $logger->error('Error occurred', ['exception' => $exception]);

        $content = file_get_contents($this->testLogPath);
        $this->assertStringContainsString('ExceptionFormattingTest.php', $content);
    }

    public function testExceptionWithPreviousException(): void
    {
        $logger = new FileLogger('dev', ['path' => $this->testLogPath]);

        $previous = new \InvalidArgumentException('Invalid input');
        $exception = new \RuntimeException('Controller failed', 0, $previous);

        $logger->error('Nested exception', ['exception' => $exception]);

        $content = file_get_contents($this->testLogPath);
        $this->assertStringContainsString('Controller failed', $content);
        $this->assertStringContainsString('Invalid input', $content);
        $this->assertStringContainsString('previous', $content);
    }

    public function testExceptionTraceIncluded(): void
    {
        $logger = new FileLogger('dev', ['path' => $this->testLogPath]);

        $exception = new \RuntimeException('Stack trace test');

        $logger->error('With trace', ['exception' => $exception]);

        $content = file_get_contents($this->testLogPath);
        $this->assertStringContainsString('trace', $content);
    }

    public function testNonExceptionContextUnchanged(): void
    {
        $logger = new FileLogger('dev', ['path' => $this->testLogPath]);

        $logger->info('Normal log', ['user_id' => 42, 'action' => 'login']);

        $content = file_get_contents($this->testLogPath);
        $this->assertStringContainsString('"user_id"', $content);
        $this->assertStringContainsString('"action":"login"', $content);
    }

    public function testContextWithNonThrowableExceptionKeyUnchanged(): void
    {
        $logger = new FileLogger('dev', ['path' => $this->testLogPath]);

        $logger->error('Not a throwable', ['exception' => 'just a string']);

        $content = file_get_contents($this->testLogPath);
        $this->assertStringContainsString('just a string', $content);
    }
}
