<?php

namespace MonkeysLegion\Logger\Logger;

use MonkeysLegion\Logger\Abstracts\AbstractLogger;
use Psr\Log\LogLevel;
use Stringable;

class ConsoleLogger extends AbstractLogger
{
    /** @var resource */
    private $outputStream;

    /** @var resource */
    private $errorStream;

    public function __construct(?string $env = null, array $config = [])
    {
        parent::__construct($env, $config);

        // Colorize is already set in parent, but we ensure it's from config
        $colorizeValue = $config['colorize'] ?? true;
        $this->colorize = is_bool($colorizeValue) ? $colorizeValue : true;

        // Set output stream (defaults to stdout, can be overridden for testing)
        $outputValue = $config['output'] ?? null;
        if (is_resource($outputValue)) {
            $this->outputStream = $outputValue;
        } else {
            $stream = fopen('php://stdout', 'w');
            $this->outputStream = $stream !== false ? $stream : STDOUT;
        }

        // Set error stream (defaults to stderr, can be overridden for testing)
        $errorValue = $config['error_output'] ?? null;
        if (is_resource($errorValue)) {
            $this->errorStream = $errorValue;
        } else {
            $stream = fopen('php://stderr', 'w');
            $this->errorStream = $stream !== false ? $stream : STDERR;
        }
    }

    public function smartLog(string|Stringable $message, array $context = []): void
    {
        $level = match ($this->env) {
            'production', 'prod' => 'info',
            'staging', 'preprod' => 'notice',
            'testing', 'test' => 'warning',
            default => 'debug',
        };

        $this->output($level, $message, $context);
    }

    public function emergency(string|Stringable $message, array $context = []): void
    {
        $context = $this->enrichContext($context);
        $this->output(LogLevel::EMERGENCY, $message, $context);
    }

    public function alert(string|Stringable $message, array $context = []): void
    {
        $context = $this->enrichContext($context);
        $this->output(LogLevel::ALERT, $message, $context);
    }

    public function critical(string|Stringable $message, array $context = []): void
    {
        $context = $this->enrichContext($context);
        $this->output(LogLevel::CRITICAL, $message, $context);
    }

    public function error(string|Stringable $message, array $context = []): void
    {
        $context = $this->enrichContext($context);
        $this->output(LogLevel::ERROR, $message, $context);
    }

    public function warning(string|Stringable $message, array $context = []): void
    {
        $context = $this->enrichContext($context);
        $this->output(LogLevel::WARNING, $message, $context);
    }

    public function notice(string|Stringable $message, array $context = []): void
    {
        $context = $this->enrichContext($context);
        $this->output(LogLevel::NOTICE, $message, $context);
    }

    public function info(string|Stringable $message, array $context = []): void
    {
        $context = $this->enrichContext($context);
        $this->output(LogLevel::INFO, $message, $context);
    }

    public function debug(string|Stringable $message, array $context = []): void
    {
        $context = $this->enrichContext($context);
        $this->output(LogLevel::DEBUG, $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        $context = $this->enrichContext($context);
        $this->output(is_string($level) ? $level : 'info', $message, $context);
    }

    /**
     * @param array<string|int, mixed> $context
     */
    private function output(string $level, string|Stringable $message, array $context): void
    {
        if (!$this->shouldLog($level)) {
            return;
        }

        $context = $this->enrichContext($context);
        $formattedMessage = $this->formatMessage($level, $message, $context);

        $color = $this->colorize ? $this->getColor($level) : '';
        $reset = $this->colorize ? "\033[0m" : '';

        $line = "{$color}{$formattedMessage}{$reset}" . PHP_EOL;

        // Route error-level and above to stderr (production best practice)
        $stream = $this->isErrorLevel($level) ? $this->errorStream : $this->outputStream;

        if (is_resource($stream)) {
            fwrite($stream, $line);
        }
    }

    /**
     * Check if level is error or above (should go to stderr).
     */
    private function isErrorLevel(string $level): bool
    {
        $normalized = $this->normalizeLogLevel($level);
        return ($this->levelPriority[$normalized] ?? 0) >= ($this->levelPriority[LogLevel::ERROR] ?? 4);
    }

    private function getColor(string $level): string
    {
        return match ($level) {
            LogLevel::EMERGENCY, LogLevel::ALERT, LogLevel::CRITICAL => "\033[1;31m", // Bold Red
            LogLevel::ERROR => "\033[0;31m", // Red
            LogLevel::WARNING => "\033[0;33m", // Yellow
            LogLevel::NOTICE => "\033[0;36m", // Cyan
            LogLevel::INFO => "\033[0;32m", // Green
            LogLevel::DEBUG => "\033[0;37m", // White
            default => "",
        };
    }
}
