<?php

namespace MonkeysLegion\Log\Logger;

use MonkeysLegion\Log\Abstracts\AbstractLogger;
use Stringable;

class NullLogger extends AbstractLogger
{
    public function __construct(?string $env = null, array $config = [])
    {
        parent::__construct($env, $config);
    }

    public function smartLog(string|Stringable $message, array $context = []): void
    {
        // No-op
    }

    public function emergency(string|Stringable $message, array $context = []): void
    {
        // No-op
    }

    public function alert(string|Stringable $message, array $context = []): void
    {
        // No-op
    }

    public function critical(string|Stringable $message, array $context = []): void
    {
        // No-op
    }

    public function error(string|Stringable $message, array $context = []): void
    {
        // No-op
    }

    public function warning(string|Stringable $message, array $context = []): void
    {
        // No-op
    }

    public function notice(string|Stringable $message, array $context = []): void
    {
        // No-op
    }

    public function info(string|Stringable $message, array $context = []): void
    {
        // No-op
    }

    public function debug(string|Stringable $message, array $context = []): void
    {
        // No-op
    }

    public function log($level, string|Stringable $message, array $context = []): void
    {
        // No-op
    }
}
