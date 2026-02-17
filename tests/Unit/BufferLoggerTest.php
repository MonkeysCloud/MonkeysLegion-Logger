<?php

namespace MonkeysLegion\Logger\Tests\Unit;

use MonkeysLegion\Logger\Logger\BufferLogger;
use MonkeysLegion\Logger\Logger\FileLogger;
use PHPUnit\Framework\TestCase;

class BufferLoggerTest extends TestCase
{
    private string $testLogPath;

    protected function setUp(): void
    {
        $this->testLogPath = sys_get_temp_dir() . '/buffer_test_' . uniqid() . '.log';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->testLogPath)) {
            unlink($this->testLogPath);
        }
    }

    public function testBufferDoesNotWriteImmediately(): void
    {
        $handler = new FileLogger('dev', ['path' => $this->testLogPath]);
        $buffer = new BufferLogger($handler);

        $buffer->info('Buffered message');

        $this->assertFileDoesNotExist($this->testLogPath);

        // Prevent auto-flush on destruct for this test
        $buffer->clear();
    }

    public function testFlushWritesAllBufferedRecords(): void
    {
        $handler = new FileLogger('dev', ['path' => $this->testLogPath]);
        $buffer = new BufferLogger($handler);

        $buffer->info('Message 1');
        $buffer->warning('Message 2');
        $buffer->error('Message 3');

        $buffer->flush();

        $content = file_get_contents($this->testLogPath);
        $this->assertStringContainsString('Message 1', $content);
        $this->assertStringContainsString('Message 2', $content);
        $this->assertStringContainsString('Message 3', $content);
    }

    public function testFlushClearsBuffer(): void
    {
        $handler = new FileLogger('dev', ['path' => $this->testLogPath]);
        $buffer = new BufferLogger($handler);

        $buffer->info('Test');
        $this->assertSame(1, $buffer->getBufferSize());

        $buffer->flush();
        $this->assertSame(0, $buffer->getBufferSize());
    }

    public function testAutoFlushOnLimit(): void
    {
        $handler = new FileLogger('dev', ['path' => $this->testLogPath]);
        $buffer = new BufferLogger($handler, bufferLimit: 3, flushOnOverflow: true);

        $buffer->info('Message 1');
        $buffer->info('Message 2');

        // Not flushed yet
        $this->assertFileDoesNotExist($this->testLogPath);

        // This triggers auto-flush (limit reached)
        $buffer->info('Message 3');

        $content = file_get_contents($this->testLogPath);
        $this->assertStringContainsString('Message 1', $content);
        $this->assertStringContainsString('Message 3', $content);
        $this->assertSame(0, $buffer->getBufferSize());
    }

    public function testEmergencyFlushesImmediately(): void
    {
        $handler = new FileLogger('dev', ['path' => $this->testLogPath]);
        $buffer = new BufferLogger($handler);

        $buffer->info('Buffered before');
        $buffer->emergency('SYSTEM DOWN');

        $content = file_get_contents($this->testLogPath);
        $this->assertStringContainsString('Buffered before', $content);
        $this->assertStringContainsString('SYSTEM DOWN', $content);
    }

    public function testClearDiscardsBufferWithoutWriting(): void
    {
        $handler = new FileLogger('dev', ['path' => $this->testLogPath]);
        $buffer = new BufferLogger($handler);

        $buffer->info('This should be discarded');
        $buffer->clear();

        $this->assertSame(0, $buffer->getBufferSize());
        $this->assertFileDoesNotExist($this->testLogPath);
    }

    public function testBufferAccumulates(): void
    {
        $handler = new FileLogger('dev', ['path' => $this->testLogPath]);
        $buffer = new BufferLogger($handler);

        for ($i = 0; $i < 10; $i++) {
            $buffer->debug("Message {$i}");
        }

        $this->assertSame(10, $buffer->getBufferSize());

        // Clean up without writing
        $buffer->clear();
    }

    public function testAllLogLevelsBuffered(): void
    {
        $handler = new FileLogger('dev', ['path' => $this->testLogPath]);
        $buffer = new BufferLogger($handler);

        $buffer->alert('Alert');
        $buffer->critical('Critical');
        $buffer->error('Error');
        $buffer->warning('Warning');
        $buffer->notice('Notice');
        $buffer->info('Info');
        $buffer->debug('Debug');

        $this->assertSame(7, $buffer->getBufferSize());

        $buffer->flush();

        $content = file_get_contents($this->testLogPath);
        $this->assertStringContainsString('Alert', $content);
        $this->assertStringContainsString('Debug', $content);
    }
}
