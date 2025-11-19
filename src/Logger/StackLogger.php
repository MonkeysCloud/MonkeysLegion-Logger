<?php

namespace MonkeysLegion\Log\Logger;

use InvalidArgumentException;
use MonkeysLegion\Log\Contracts\MonkeysLoggerInterface;
use MonkeysLegion\Log\Factory\LoggerFactory;
use Stringable;

class StackLogger implements MonkeysLoggerInterface
{
    /** @var MonkeysLoggerInterface[] */
    private array $loggers = [];

    /** @var array<string> */
    private array $channelNames;

    private ?LoggerFactory $factory;

    /**
     * @param array<string> $channelNames
     */
    public function __construct(array $channelNames = [], ?LoggerFactory $factory = null)
    {
        $this->channelNames = array_unique($channelNames);
        $this->factory = $factory;
    }

    /**
     * Set the logger factory for resolving channel names.
     */
    public function setFactory(LoggerFactory $factory): self
    {
        $this->factory = $factory;
        return $this;
    }

    /**
     * Get or resolve loggers from channel names.
     *
     * @return MonkeysLoggerInterface[]
     */
    private function getLoggers(): array
    {
        if (empty($this->loggers) && !empty($this->channelNames) && $this->factory !== null) {
            $this->loggers = $this->resolveLoggers();
        }

        return $this->loggers;
    }

    /**
     * Resolve logger instances from channel names.
     *
     * @return MonkeysLoggerInterface[]
     */
    private function resolveLoggers(): array
    {
        $loggers = [];
        $resolved = [];

        foreach ($this->channelNames as $channelName) {
            // Skip if already resolved (prevent duplicates)
            if (isset($resolved[$channelName])) {
                continue;
            }

            try {
                $logger = $this->factory?->make($channelName);

                // Prevent infinite recursion - skip stack loggers and null loggers
                if ($logger !== null && !($logger instanceof self)) {
                    $loggers[] = $logger;
                    $resolved[$channelName] = true;
                } 
                if ($logger instanceof self) {
                    throw new InvalidArgumentException("Circular dependency detected for logger channel '{$channelName}'.");
                }
            } catch (InvalidArgumentException $e) {
                throw $e;
            } catch (\Exception $e) {
                // Skip invalid channels silently
                continue;
            }
        }

        return $loggers;
    }

    /**
     * Add a logger instance directly.
     */
    public function addLogger(MonkeysLoggerInterface $logger): self
    {
        // Prevent adding stack loggers to avoid recursion
        if (!($logger instanceof self)) {
            $this->loggers[] = $logger;
        }

        return $this;
    }

    public function smartLog(string|Stringable $message, array $context = []): void
    {
        foreach ($this->getLoggers() as $logger) {
            $logger->smartLog($message, $context);
        }
    }

    public function emergency(string|Stringable $message, array $context = []): void
    {
        foreach ($this->getLoggers() as $logger) {
            $logger->emergency($message, $context);
        }
    }

    public function alert(string|Stringable $message, array $context = []): void
    {
        foreach ($this->getLoggers() as $logger) {
            $logger->alert($message, $context);
        }
    }

    public function critical(string|Stringable $message, array $context = []): void
    {
        foreach ($this->getLoggers() as $logger) {
            $logger->critical($message, $context);
        }
    }

    public function error(string|Stringable $message, array $context = []): void
    {
        foreach ($this->getLoggers() as $logger) {
            $logger->error($message, $context);
        }
    }

    public function warning(string|Stringable $message, array $context = []): void
    {
        foreach ($this->getLoggers() as $logger) {
            $logger->warning($message, $context);
        }
    }

    public function notice(string|Stringable $message, array $context = []): void
    {
        foreach ($this->getLoggers() as $logger) {
            $logger->notice($message, $context);
        }
    }

    public function info(string|Stringable $message, array $context = []): void
    {
        foreach ($this->getLoggers() as $logger) {
            $logger->info($message, $context);
        }
    }

    public function debug(string|Stringable $message, array $context = []): void
    {
        foreach ($this->getLoggers() as $logger) {
            $logger->debug($message, $context);
        }
    }

    public function log($level, string|Stringable $message, array $context = []): void
    {
        foreach ($this->getLoggers() as $logger) {
            $logger->log($level, $message, $context);
        }
    }
}
