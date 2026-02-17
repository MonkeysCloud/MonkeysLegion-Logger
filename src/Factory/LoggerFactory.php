<?php

namespace MonkeysLegion\Logger\Factory;

use InvalidArgumentException;
use MonkeysLegion\Logger\Contracts\FormatterInterface;
use MonkeysLegion\Logger\Contracts\MonkeysLoggerInterface;
use MonkeysLegion\Logger\Contracts\ProcessorInterface;
use MonkeysLegion\Logger\Formatter\JsonFormatter;
use MonkeysLegion\Logger\Formatter\LineFormatter;
use MonkeysLegion\Logger\Logger\BufferLogger;
use MonkeysLegion\Logger\Logger\ConsoleLogger;
use MonkeysLegion\Logger\Logger\FileLogger;
use MonkeysLegion\Logger\Logger\NativeLogger;
use MonkeysLegion\Logger\Logger\NullLogger;
use MonkeysLegion\Logger\Logger\StackLogger;
use MonkeysLegion\Logger\Logger\SyslogLogger;
use MonkeysLegion\Logger\Abstracts\AbstractLogger;

class LoggerFactory
{
    /** @var array<string, mixed> */
    private array $config;
    private string $env;

    /** @var array<string, bool> */
    private array $resolving = [];

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config = [], ?string $env = null)
    {
        $this->config = $config;
        $envValue = $env ?? $_ENV['APP_ENV'] ?? 'dev';
        $this->env = is_string($envValue) ? $envValue : 'dev';
    }

    /**
     * Create a logger instance based on channel configuration.
     */
    public function make(?string $channel = null): MonkeysLoggerInterface
    {
        $defaultChannel = $this->config['default'] ?? 'stack';
        $channel = $channel ?? (is_string($defaultChannel) ? $defaultChannel : 'stack');
        // Detect circular dependencies
        if (isset($this->resolving[$channel])) {
            throw new InvalidArgumentException("Circular dependency detected for logger channel '{$channel}'.");
        }

        $channelsConfig = $this->config['channels'] ?? [];
        if (!is_array($channelsConfig) || !isset($channelsConfig[$channel])) {
            throw new InvalidArgumentException("Logger channel '{$channel}' is not configured.");
        }

        $this->resolving[$channel] = true;

        try {
            $channelConfig = $channelsConfig[$channel];

            if (!is_array($channelConfig)) {
                throw new InvalidArgumentException("Invalid configuration for channel '{$channel}'.");
            }

            /** @var array<string, mixed> $channelConfig */
            $driver = $channelConfig['driver'] ?? 'null';
            $driver = is_string($driver) ? $driver : 'null';

            $logger = match ($driver) {
                'stack'    => $this->createStackLogger($channelConfig),
                'file'     => $this->createFileLogger($channelConfig, $channel),
                'console'  => $this->createConsoleLogger($channelConfig, $channel),
                'syslog'   => $this->createSyslogLogger($channelConfig, $channel),
                'errorlog' => $this->createNativeLogger($channelConfig, $channel),
                'buffer'   => $this->createBufferLogger($channelConfig, $channel),
                'null'     => $this->createNullLogger($channelConfig, $channel),
                default    => throw new InvalidArgumentException("Unsupported logger driver: {$driver}"),
            };

            return $logger;
        } finally {
            unset($this->resolving[$channel]);
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    private function createStackLogger(array $config): MonkeysLoggerInterface
    {
        $channelsValue = $config['channels'] ?? [];
        $channels = is_array($channelsValue) ? $channelsValue : [];

        // Remove duplicates and filter out invalid entries
        $channels = array_values(array_unique(array_filter($channels, 'is_string')));

        // Check for circular dependencies in stack channels
        foreach ($channels as $channelName) {
            if (isset($this->resolving[$channelName])) {
                throw new InvalidArgumentException("Circular dependency detected for logger channel '{$channelName}'.");
            }
        }

        $stackLogger = new StackLogger($channels, $this);

        return $stackLogger;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function createFileLogger(array $config, string $channelName = 'file'): MonkeysLoggerInterface
    {
        $pathValue = $config['path'] ?? 'logs/app.log';
        $path = is_string($pathValue) ? $pathValue : 'logs/app.log';

        // Check if this is a daily driver - add date to filename automatically
        $isDaily = isset($config['daily']) && $config['daily'] === true;

        if ($isDaily) {
            $dateFormat = $this->getStringConfig($config, 'date_format', 'Y-m-d');
            $dateStr = date($dateFormat);

            // Insert date before file extension
            // logs/app.log -> logs/app-2024-01-15.log
            $pathInfo = pathinfo($path);
            $directory = $pathInfo['dirname'] ?? '.';
            $filename = $pathInfo['filename'];
            $extension = isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '';

            $path = $directory . '/' . $filename . '-' . $dateStr . $extension;
        }

        // Ensure directory exists
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $logger = new FileLogger($this->env, [
            'path'    => $path,
            'level'   => $this->getStringConfig($config, 'level', 'debug'),
            'format'  => $this->getStringConfig($config, 'format', '[{timestamp}] [{channel}.{env}] {level}: {message} {context}'),
            'channel' => $channelName,
        ]);

        return $this->applyFormatterAndProcessors($logger, $config);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function createConsoleLogger(array $config, string $channelName = 'console'): MonkeysLoggerInterface
    {
        $colorizeValue = $config['colorize'] ?? true;

        $logger = new ConsoleLogger($this->env, [
            'level'    => $this->getStringConfig($config, 'level', 'debug'),
            'format'   => $this->getStringConfig($config, 'format', '[{channel}.{env}] {level}: {message} {context}'),
            'colorize' => is_bool($colorizeValue) ? $colorizeValue : true,
            'channel'  => $channelName,
        ]);

        return $this->applyFormatterAndProcessors($logger, $config);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function createSyslogLogger(array $config, string $channelName = 'syslog'): MonkeysLoggerInterface
    {
        $identValue = $config['ident'] ?? 'php';
        $facilityValue = $config['facility'] ?? LOG_USER;

        $logger = new SyslogLogger($this->env, [
            'level'    => $this->getStringConfig($config, 'level', 'debug'),
            'format'   => $this->getStringConfig($config, 'format', '[{channel}.{env}] {level}: {message} {context}'),
            'ident'    => is_string($identValue) ? $identValue : 'php',
            'facility' => is_int($facilityValue) ? $facilityValue : LOG_USER,
            'channel'  => $channelName,
        ]);

        return $this->applyFormatterAndProcessors($logger, $config);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function createNativeLogger(array $config, string $channelName = 'errorlog'): MonkeysLoggerInterface
    {
        $messageTypeValue = $config['message_type'] ?? 0;
        $destinationValue = $config['destination'] ?? null;

        $logger = new NativeLogger($this->env, [
            'level'        => $this->getStringConfig($config, 'level', 'debug'),
            'format'       => $this->getStringConfig($config, 'format', '[{channel}.{env}] {level}: {message} {context}'),
            'message_type' => is_int($messageTypeValue) ? $messageTypeValue : 0,
            'destination'  => is_string($destinationValue) ? $destinationValue : null,
            'channel'      => $channelName,
        ]);

        return $this->applyFormatterAndProcessors($logger, $config);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function createBufferLogger(array $config, string $channelName = 'buffer'): MonkeysLoggerInterface
    {
        $handlerChannel = $this->getStringConfig($config, 'handler', 'file');

        $handler = $this->make($handlerChannel);

        $limitValue = $config['buffer_limit'] ?? 0;
        $bufferLimit = is_int($limitValue) ? $limitValue : 0;

        $flushValue = $config['flush_on_overflow'] ?? true;
        $flushOnOverflow = is_bool($flushValue) ? $flushValue : true;

        return new BufferLogger($handler, $bufferLimit, $flushOnOverflow);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function createNullLogger(array $config, string $channelName = 'null'): MonkeysLoggerInterface
    {
        return new NullLogger($this->env, [
            'level'   => $this->getStringConfig($config, 'level', 'debug'),
            'channel' => $channelName,
        ]);
    }

    /**
     * Apply formatter and processors from channel configuration to a logger.
     *
     * @param array<string, mixed> $config
     */
    private function applyFormatterAndProcessors(AbstractLogger $logger, array $config): AbstractLogger
    {
        // Apply formatter
        $formatterType = $this->getStringConfig($config, 'formatter', '');
        if ($formatterType !== '') {
            $formatter = $this->createFormatter($formatterType, $config);
            if ($formatter !== null) {
                $logger->setFormatter($formatter);
            }
        }

        // Apply processors
        $processorsValue = $config['processors'] ?? [];
        if (is_array($processorsValue)) {
            foreach ($processorsValue as $processorConfig) {
                $processor = $this->createProcessor($processorConfig);
                if ($processor !== null) {
                    $logger->addProcessor($processor);
                }
            }
        }

        return $logger;
    }

    /**
     * Create a formatter from a type string.
     *
     * @param array<string, mixed> $config
     */
    private function createFormatter(string $type, array $config): ?FormatterInterface
    {
        return match ($type) {
            'json' => new JsonFormatter(
                prettyPrint: ($config['json_pretty_print'] ?? false) === true,
            ),
            'line' => new LineFormatter(
                format: $this->getStringConfig($config, 'format', '[{timestamp}] [{channel}.{env}] {level}: {message} {context}'),
            ),
            default => null,
        };
    }

    /**
     * Create a processor from configuration (string class name or array with 'class' key).
     */
    private function createProcessor(mixed $processorConfig): ?ProcessorInterface
    {
        $className = null;

        if (is_string($processorConfig)) {
            $className = $processorConfig;
        } elseif (is_array($processorConfig) && isset($processorConfig['class']) && is_string($processorConfig['class'])) {
            $className = $processorConfig['class'];
        }

        if ($className === null || !class_exists($className)) {
            return null;
        }

        $instance = new $className();

        return $instance instanceof ProcessorInterface ? $instance : null;
    }

    /**
     * Safely get a string config value.
     *
     * @param array<string, mixed> $config
     */
    private function getStringConfig(array $config, string $key, string $default): string
    {
        $value = $config[$key] ?? $default;
        return is_string($value) ? $value : $default;
    }
}
