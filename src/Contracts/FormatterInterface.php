<?php

namespace MonkeysLegion\Logger\Contracts;

use Stringable;

/**
 * Formats a log record into a string ready for output.
 */
interface FormatterInterface
{
    /**
     * @param array<string|int, mixed> $context
     * @param array<string, mixed>     $extra   Processor-injected data
     */
    public function format(
        string          $level,
        string|Stringable $message,
        array           $context = [],
        array           $extra = [],
        string          $channel = 'app',
        string          $env = 'dev',
    ): string;
}
