<?php

namespace MonkeysLegion\Logger\Contracts;

use Psr\Log\LoggerInterface;
use Stringable;

interface MonkeysLoggerInterface extends LoggerInterface
{
    /**
     * Log a message at the appropriate level based on the content of the message.
     *
     * @param string $message The log message.
     * @param array<string, mixed> $context The log context.
     */
    public function smartLog(string|Stringable $message, array $context = []): void;
}
