<?php

namespace MonkeysLegion\Log\Logger;

use MonkeysLegion\Log\Abstracts\AbstractLogger;
use Psr\Log\LogLevel;
use Stringable;

class FileLogger extends AbstractLogger
{
    /**
     * @param array<string, mixed> $context
     */
    public function smartLog(string|Stringable $message, array $context = []): void
    {
        $level = match ($this->env) {
            'production', 'prod' => 'info',
            'staging', 'preprod' => 'notice',
            'testing', 'test' => 'warning',
            default => 'debug',
        };

        $this->writeLog($level, $message, $context);
    }

    /**
     * @param array<string|int, mixed> $context
     */
    public function emergency(string|Stringable $message, array $context = []): void
    {
        $this->writeLog(LogLevel::EMERGENCY, $message, $context);
    }

    /**
     * @param array<string|int, mixed> $context
     */
    public function alert(string|Stringable $message, array $context = []): void
    {
        $this->writeLog(LogLevel::ALERT, $message, $context);
    }

    /**
     * @param array<string|int, mixed> $context
     */
    public function critical(string|Stringable $message, array $context = []): void
    {
        $this->writeLog(LogLevel::CRITICAL, $message, $context);
    }

    /**
     * @param array<string|int, mixed> $context
     */
    public function error(string|Stringable $message, array $context = []): void
    {
        $this->writeLog(LogLevel::ERROR, $message, $context);
    }

    /**
     * @param array<string|int, mixed> $context
     */
    public function warning(string|Stringable $message, array $context = []): void
    {
        $this->writeLog(LogLevel::WARNING, $message, $context);
    }

    /**
     * @param array<string|int, mixed> $context
     */
    public function notice(string|Stringable $message, array $context = []): void
    {
        $this->writeLog(LogLevel::NOTICE, $message, $context);
    }

    /**
     * @param array<string|int, mixed> $context
     */
    public function info(string|Stringable $message, array $context = []): void
    {
        $this->writeLog(LogLevel::INFO, $message, $context);
    }

    /**
     * @param array<string|int, mixed> $context
     */
    public function debug(string|Stringable $message, array $context = []): void
    {
        $this->writeLog(LogLevel::DEBUG, $message, $context);
    }

    /**
     * @param array<string|int, mixed> $context
     */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        $this->writeLog(is_string($level) ? $level : 'info', $message, $context);
    }

    /**
     * @param array<string|int, mixed> $context
     */
    private function writeLog(string $level, string|Stringable $message, array $context): void
    {
        if (!$this->shouldLog($level)) {
            return;
        }

        $context = $this->enrichContext($context);
        $logLine = $this->formatMessage($level, $message, $context) . PHP_EOL;

        file_put_contents($this->logPath, $logLine, FILE_APPEND | LOCK_EX);
    }
}
