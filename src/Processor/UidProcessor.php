<?php

namespace MonkeysLegion\Logger\Processor;

use MonkeysLegion\Logger\Contracts\ProcessorInterface;

/**
 * Generates a unique ID per request/process for log correlation.
 *
 * All log entries within the same request will share the same UID,
 * making it easy to trace a single request across multiple log lines.
 */
class UidProcessor implements ProcessorInterface
{
    private string $uid;
    private int $length;

    public function __construct(int $length = 8)
    {
        $this->length = max(4, min(32, $length));
        $this->uid    = $this->generateUid();
    }

    public function __invoke(array $record): array
    {
        if (!isset($record['extra']) || !is_array($record['extra'])) {
            $record['extra'] = [];
        }

        $record['extra']['uid'] = $this->uid;

        return $record;
    }

    /**
     * Get the current UID value.
     */
    public function getUid(): string
    {
        return $this->uid;
    }

    /**
     * Reset the UID (useful for worker processes handling multiple requests).
     */
    public function reset(): void
    {
        $this->uid = $this->generateUid();
    }

    private function generateUid(): string
    {
        /** @var int<1, max> $bytes */
        $bytes = max(1, (int) ceil($this->length / 2));
        return substr(bin2hex(random_bytes($bytes)), 0, $this->length);
    }
}
