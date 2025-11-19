<?php

namespace MonkeysLegion\Log\Factory;

use InvalidArgumentException;
use MonkeysLegion\Log\Contracts\MonkeysLoggerInterface;
use MonkeysLegion\Log\Logger\ConsoleLogger;
use MonkeysLegion\Log\Logger\FileLogger;
use MonkeysLegion\Log\Logger\NativeLogger;
use MonkeysLegion\Log\Logger\NullLogger;
use MonkeysLegion\Log\Logger\StackLogger;
use MonkeysLegion\Log\Logger\SyslogLogger;

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
        echo "Making logger for channel: {$channel}\n";
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
                'stack' => $this->createStackLogger($channelConfig),
                'file' => $this->createFileLogger($channelConfig),
                'console' => $this->createConsoleLogger($channelConfig),
                'syslog' => $this->createSyslogLogger($channelConfig),
                'errorlog' => $this->createNativeLogger($channelConfig),
                'null' => $this->createNullLogger($channelConfig),
                default => throw new InvalidArgumentException("Unsupported logger driver: {$driver}"),
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
            echo "Checking channel: {$channelName}\n";
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
    private function createFileLogger(array $config): MonkeysLoggerInterface
    {
        $pathValue = $config['path'] ?? 'logs/app.log';
        $path = is_string($pathValue) ? $pathValue : 'logs/app.log';

        // Handle date placeholders in path
        if (str_contains($path, '{date}')) {
            $dateFormatValue = $config['date_format'] ?? 'Y-m-d';
            $dateFormat = is_string($dateFormatValue) ? $dateFormatValue : 'Y-m-d';
            $path = str_replace('{date}', date($dateFormat), $path);
        }

        // Ensure directory exists
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        return new FileLogger($this->env, [
            'path' => $path,
            'level' => $this->getStringConfig($config, 'level', 'debug'),
            'format' => $this->getStringConfig($config, 'format', '[{timestamp}] [{env}] {level}: {message} {context}'),
        ]);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function createConsoleLogger(array $config): MonkeysLoggerInterface
    {
        $colorizeValue = $config['colorize'] ?? true;

        return new ConsoleLogger($this->env, [
            'level' => $this->getStringConfig($config, 'level', 'debug'),
            'format' => $this->getStringConfig($config, 'format', '[{env}] {level}: {message} {context}'),
            'colorize' => is_bool($colorizeValue) ? $colorizeValue : true,
        ]);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function createSyslogLogger(array $config): MonkeysLoggerInterface
    {
        $identValue = $config['ident'] ?? 'php';
        $facilityValue = $config['facility'] ?? LOG_USER;

        return new SyslogLogger($this->env, [
            'level' => $this->getStringConfig($config, 'level', 'debug'),
            'format' => $this->getStringConfig($config, 'format', '[{env}] {level}: {message} {context}'),
            'ident' => is_string($identValue) ? $identValue : 'php',
            'facility' => is_int($facilityValue) ? $facilityValue : LOG_USER,
        ]);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function createNativeLogger(array $config): MonkeysLoggerInterface
    {
        $messageTypeValue = $config['message_type'] ?? 0;
        $destinationValue = $config['destination'] ?? null;

        return new NativeLogger($this->env, [
            'level' => $this->getStringConfig($config, 'level', 'debug'),
            'format' => $this->getStringConfig($config, 'format', '[{env}] {level}: {message} {context}'),
            'message_type' => is_int($messageTypeValue) ? $messageTypeValue : 0,
            'destination' => is_string($destinationValue) ? $destinationValue : null,
        ]);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function createNullLogger(array $config): MonkeysLoggerInterface
    {
        return new NullLogger($this->env, [
            'level' => $this->getStringConfig($config, 'level', 'debug'),
        ]);
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
