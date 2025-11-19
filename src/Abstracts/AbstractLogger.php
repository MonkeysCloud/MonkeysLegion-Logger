<?php

namespace MonkeysLegion\Log\Abstracts;

use MonkeysLegion\Log\Contracts\MonkeysLoggerInterface;
use Psr\Log\LogLevel;

abstract class AbstractLogger implements MonkeysLoggerInterface
{
    protected string $env;
    protected bool $colorize;
    protected string $logPath;
    protected string $minLevel;
    protected string $format;

    /** @var array<string, int> */
    protected array $levelPriority = [
        LogLevel::DEBUG => 0,
        LogLevel::INFO => 1,
        LogLevel::NOTICE => 2,
        LogLevel::WARNING => 3,
        LogLevel::ERROR => 4,
        LogLevel::CRITICAL => 5,
        LogLevel::ALERT => 6,
        LogLevel::EMERGENCY => 7,
    ];

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(?string $env = null, array $config = [])
    {
        $envValue = $env ?? $_ENV['APP_ENV'] ?? 'dev';
        $this->env = strtolower(is_string($envValue) ? $envValue : 'dev');

        $levelValue = $config['level'] ?? 'debug';
        $this->minLevel = $this->normalizeLogLevel(is_string($levelValue) ? $levelValue : 'debug');

        $colorizeValue = $config['colorize'] ?? false;
        $this->colorize = is_bool($colorizeValue) ? $colorizeValue : false;

        $pathValue = $config['path'] ?? '';
        $this->logPath = is_string($pathValue) ? $pathValue : '';

        $formatValue = $config['format'] ?? '[{timestamp}] [{env}] {level}: {message} {context}';
        $this->format = is_string($formatValue) ? $formatValue : '[{timestamp}] [{env}] {level}: {message} {context}';
    }

    /**
     * Normalize log level string to PSR-3 standard.
     */
    protected function normalizeLogLevel(string $level): string
    {
        $level = strtolower($level);

        // Map common variations to PSR-3 standard levels
        return match ($level) {
            'debug', 'trace' => LogLevel::DEBUG,
            'info', 'information' => LogLevel::INFO,
            'notice', 'note' => LogLevel::NOTICE,
            'warning', 'warn' => LogLevel::WARNING,
            'error', 'err' => LogLevel::ERROR,
            'critical', 'crit', 'fatal' => LogLevel::CRITICAL,
            'alert' => LogLevel::ALERT,
            'emergency', 'emerg', 'panic' => LogLevel::EMERGENCY,
            default => LogLevel::DEBUG,
        };
    }

    /**
     * Check if a log level should be logged based on minimum level.
     */
    protected function shouldLog(string $level): bool
    {
        $normalizedLevel = $this->normalizeLogLevel($level);
        $levelPriority = $this->levelPriority[$normalizedLevel] ?? 0;
        $minPriority = $this->levelPriority[$this->minLevel] ?? 0;

        return $levelPriority >= $minPriority;
    }

    /**
     * Format log message using the configured pattern.
     *
     * @param string $level
     * @param string|\Stringable $message
     * @param array<string|int, mixed> $context
     * @return string
     */
    protected function formatMessage(string $level, string|\Stringable $message, array $context): string
    {
        $timestamp = date('Y-m-d H:i:s');
        $contextJson = !empty($context) ? json_encode($context) : '';

        $replacements = [
            '{timestamp}' => $timestamp,
            '{env}' => $this->env,
            '{level}' => strtoupper($level),
            '{message}' => (string) $message,
            '{context}' => is_string($contextJson) ? $contextJson : '',
        ];

        $search = array_keys($replacements);
        $replace = array_values($replacements);

        return str_replace($search, $replace, $this->format);
    }

    /**
     * Enrich context with environment information.
     *
     * @param array<string|int, mixed> $context
     * @return array<string|int, mixed>
     */
    protected function enrichContext(array $context = []): array
    {
        return array_merge(['env' => $this->env], $context);
    }
}
