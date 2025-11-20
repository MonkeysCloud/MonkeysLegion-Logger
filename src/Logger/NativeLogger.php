<?php

namespace MonkeysLegion\Logger\Logger;

use MonkeysLegion\Logger\Abstracts\AbstractLogger;
use Psr\Log\LogLevel;
use Stringable;

class NativeLogger extends AbstractLogger
{
    /** @var 0|1|3|4 */
    private int $messageType;
    private ?string $destination;

    public function __construct(?string $env = null, array $config = [])
    {
        parent::__construct($env, $config);

        $messageTypeValue = $config['message_type'] ?? 0;
        $validTypes = [0, 1, 3, 4];
        $this->messageType = (is_int($messageTypeValue) && in_array($messageTypeValue, $validTypes, true))
            ? $messageTypeValue
            : 0;

        $destinationValue = $config['destination'] ?? null;
        $this->destination = is_string($destinationValue) ? $destinationValue : null;
    }

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

    public function emergency(string|Stringable $message, array $context = []): void
    {
        $context = $this->enrichContext($context);
        $this->writeLog(LogLevel::EMERGENCY, $message, $context);
    }

    public function alert(string|Stringable $message, array $context = []): void
    {
        $context = $this->enrichContext($context);
        $this->writeLog(LogLevel::ALERT, $message, $context);
    }

    public function critical(string|Stringable $message, array $context = []): void
    {
        $context = $this->enrichContext($context);
        $this->writeLog(LogLevel::CRITICAL, $message, $context);
    }

    public function error(string|Stringable $message, array $context = []): void
    {
        $context = $this->enrichContext($context);
        $this->writeLog(LogLevel::ERROR, $message, $context);
    }

    public function warning(string|Stringable $message, array $context = []): void
    {
        $context = $this->enrichContext($context);
        $this->writeLog(LogLevel::WARNING, $message, $context);
    }

    public function notice(string|Stringable $message, array $context = []): void
    {
        $context = $this->enrichContext($context);
        $this->writeLog(LogLevel::NOTICE, $message, $context);
    }

    public function info(string|Stringable $message, array $context = []): void
    {
        $context = $this->enrichContext($context);
        $this->writeLog(LogLevel::INFO, $message, $context);
    }

    public function debug(string|Stringable $message, array $context = []): void
    {
        $context = $this->enrichContext($context);
        $this->writeLog(LogLevel::DEBUG, $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        $context = $this->enrichContext($context);
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
        $logLine = $this->formatMessage($level, $message, $context);

        if ($this->destination !== null) {
            error_log($logLine, $this->messageType, $this->destination);
        } else {
            error_log($logLine, $this->messageType);
        }
    }
}
