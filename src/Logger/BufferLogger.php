<?php

namespace MonkeysLegion\Logger\Logger;

use MonkeysLegion\Logger\Contracts\MonkeysLoggerInterface;
use Stringable;

/**
 * Buffers log records in memory and flushes them to a wrapped logger.
 *
 * Ideal for long-running workers, queue consumers, and cron jobs where
 * you want to batch I/O writes or defer logging until a unit of work completes.
 */
class BufferLogger implements MonkeysLoggerInterface
{
    /** @var array<int, array{level: string, message: string|Stringable, context: array<string|int, mixed>}> */
    private array $buffer = [];

    private MonkeysLoggerInterface $handler;
    private int $bufferLimit;
    private bool $flushOnOverflow;

    /**
     * @param MonkeysLoggerInterface $handler        The underlying logger to flush to
     * @param int                    $bufferLimit     Max buffered records before auto-flush (0 = unlimited)
     * @param bool                   $flushOnOverflow Whether to auto-flush when limit is reached
     */
    public function __construct(
        MonkeysLoggerInterface $handler,
        int  $bufferLimit = 0,
        bool $flushOnOverflow = true,
    ) {
        $this->handler         = $handler;
        $this->bufferLimit     = max(0, $bufferLimit);
        $this->flushOnOverflow = $flushOnOverflow;
    }

    public function __destruct()
    {
        $this->flush();
    }

    /**
     * Flush all buffered records to the underlying logger.
     */
    public function flush(): void
    {
        foreach ($this->buffer as $record) {
            $this->handler->log($record['level'], $record['message'], $record['context']);
        }
        $this->buffer = [];
    }

    /**
     * Clear the buffer without flushing.
     */
    public function clear(): void
    {
        $this->buffer = [];
    }

    /**
     * Get the current buffer size.
     */
    public function getBufferSize(): int
    {
        return count($this->buffer);
    }

    public function smartLog(string|Stringable $message, array $context = []): void
    {
        // SmartLog cannot be easily buffered since level depends on env,
        // so we delegate directly.
        $this->handler->smartLog($message, $context);
    }

    public function emergency(string|Stringable $message, array $context = []): void
    {
        // Emergency is always flushed immediately — never buffer emergencies
        $this->flush();
        $this->handler->emergency($message, $context);
    }

    public function alert(string|Stringable $message, array $context = []): void
    {
        $this->addToBuffer('alert', $message, $context);
    }

    public function critical(string|Stringable $message, array $context = []): void
    {
        $this->addToBuffer('critical', $message, $context);
    }

    public function error(string|Stringable $message, array $context = []): void
    {
        $this->addToBuffer('error', $message, $context);
    }

    public function warning(string|Stringable $message, array $context = []): void
    {
        $this->addToBuffer('warning', $message, $context);
    }

    public function notice(string|Stringable $message, array $context = []): void
    {
        $this->addToBuffer('notice', $message, $context);
    }

    public function info(string|Stringable $message, array $context = []): void
    {
        $this->addToBuffer('info', $message, $context);
    }

    public function debug(string|Stringable $message, array $context = []): void
    {
        $this->addToBuffer('debug', $message, $context);
    }

    /**
     * @param array<string|int, mixed> $context
     */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        $this->addToBuffer(is_string($level) ? $level : 'info', $message, $context);
    }

    /**
     * @param array<string|int, mixed> $context
     */
    private function addToBuffer(string $level, string|Stringable $message, array $context): void
    {
        $this->buffer[] = [
            'level'   => $level,
            'message' => $message,
            'context' => $context,
        ];

        if ($this->flushOnOverflow && $this->bufferLimit > 0 && count($this->buffer) >= $this->bufferLimit) {
            $this->flush();
        }
    }
}
