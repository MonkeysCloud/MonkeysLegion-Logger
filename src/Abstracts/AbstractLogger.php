<?php

namespace MonkeysLegion\Logger\Abstracts;

use MonkeysLegion\Logger\Contracts\FormatterInterface;
use MonkeysLegion\Logger\Contracts\MonkeysLoggerInterface;
use MonkeysLegion\Logger\Contracts\ProcessorInterface;
use MonkeysLegion\Logger\Formatter\LineFormatter;
use Psr\Log\LogLevel;

abstract class AbstractLogger implements MonkeysLoggerInterface
{
    protected string $env;
    protected bool $colorize;
    protected string $logPath;
    protected string $minLevel;
    protected string $format;
    protected string $channel;

    protected ?FormatterInterface $formatter = null;

    /** @var ProcessorInterface[] */
    protected array $processors = [];

    /** @var array<string, int> */
    protected array $levelPriority = [
        LogLevel::DEBUG     => 0,
        LogLevel::INFO      => 1,
        LogLevel::NOTICE    => 2,
        LogLevel::WARNING   => 3,
        LogLevel::ERROR     => 4,
        LogLevel::CRITICAL  => 5,
        LogLevel::ALERT     => 6,
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

        $formatValue = $config['format'] ?? '[{timestamp}] [{channel}.{env}] {level}: {message} {context}';
        $this->format = is_string($formatValue) ? $formatValue : '[{timestamp}] [{channel}.{env}] {level}: {message} {context}';

        $channelValue = $config['channel'] ?? 'app';
        $this->channel = is_string($channelValue) ? $channelValue : 'app';
    }

    /**
     * Set the formatter for this logger.
     *
     * @return static
     */
    public function setFormatter(FormatterInterface $formatter): static
    {
        $this->formatter = $formatter;
        return $this;
    }

    /**
     * Get the formatter, falling back to LineFormatter.
     */
    public function getFormatter(): FormatterInterface
    {
        if ($this->formatter === null) {
            $this->formatter = new LineFormatter($this->format);
        }
        return $this->formatter;
    }

    /**
     * Add a processor to the logger.
     *
     * @return static
     */
    public function addProcessor(ProcessorInterface $processor): static
    {
        $this->processors[] = $processor;
        return $this;
    }

    /**
     * Get the channel name.
     */
    public function getChannel(): string
    {
        return $this->channel;
    }

    /**
     * Set the channel name.
     *
     * @return static
     */
    public function setChannel(string $channel): static
    {
        $this->channel = $channel;
        return $this;
    }

    /**
     * Normalize log level string to PSR-3 standard.
     */
    protected function normalizeLogLevel(string $level): string
    {
        $level = strtolower($level);

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
     * Build a log record, run processors, and format it.
     *
     * @param array<string|int, mixed> $context
     */
    protected function formatMessage(string $level, string|\Stringable $message, array $context): string
    {
        // Normalize exception in context
        $context = $this->normalizeException($context);

        // Build the record for processors
        $record = [
            'level'   => $level,
            'message' => (string) $message,
            'context' => $context,
            'extra'   => [],
            'channel' => $this->channel,
        ];

        // Run processors
        foreach ($this->processors as $processor) {
            $record = $processor($record);
        }

        /** @var array<string|int, mixed> $recordContext */
        $recordContext = is_array($record['context']) ? $record['context'] : $context;

        /** @var array<string, mixed> $recordExtra */
        $recordExtra = is_array($record['extra'] ?? null) ? $record['extra'] : [];

        // Allow processors to adjust level, message, and channel
        $recordLevel   = isset($record['level']) && is_string($record['level']) ? $record['level'] : $level;
        $recordMessage = isset($record['message']) ? (string) $record['message'] : (string) $message;
        $recordChannel = isset($record['channel']) && is_string($record['channel']) ? $record['channel'] : $this->channel;

        return $this->getFormatter()->format(
            $recordLevel,
            $recordMessage,
            $recordContext,
            $recordExtra,
            $recordChannel,
            $this->env,
        );
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

    /**
     * Normalize any Throwable in the 'exception' context key into a structured array.
     *
     * This is the key feature for production debugging — when a controller
     * throws an exception, this ensures the full details (message, file, line,
     * trace) are captured in the log, not just a generic "500 error".
     *
     * @param array<string|int, mixed> $context
     * @return array<string|int, mixed>
     */
    protected function normalizeException(array $context): array
    {
        if (!isset($context['exception']) || !($context['exception'] instanceof \Throwable)) {
            return $context;
        }

        $exception = $context['exception'];
        $context['exception'] = $this->formatThrowable($exception);

        return $context;
    }

    /**
     * Format a Throwable into a structured array.
     *
     * @return array<string, mixed>
     */
    private function formatThrowable(\Throwable $e): array
    {
        $data = [
            'class'   => $e::class,
            'message' => $e->getMessage(),
            'code'    => $e->getCode(),
            'file'    => $e->getFile() . ':' . $e->getLine(),
            'trace'   => $this->formatTrace($e),
        ];

        if ($e->getPrevious() !== null) {
            $data['previous'] = $this->formatThrowable($e->getPrevious());
        }

        return $data;
    }

    /**
     * Format exception trace into a concise array of strings.
     *
     * @return list<string>
     */
    private function formatTrace(\Throwable $e): array
    {
        $lines = [];
        foreach ($e->getTrace() as $i => $frame) {
            $file     = $frame['file'] ?? 'unknown';
            $line     = $frame['line'] ?? 0;
            $class    = $frame['class'] ?? '';
            $type     = $frame['type'] ?? '';
            $function = $frame['function'];

            $lines[] = "#{$i} {$file}:{$line} {$class}{$type}{$function}()";
            if ($i >= 15) {
                $lines[] = '... ' . (count($e->getTrace()) - 16) . ' more frames';
                break;
            }
        }
        return $lines;
    }
}
