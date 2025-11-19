<?php

namespace MonkeysLegion\Log\Logger;

use MonkeysLegion\Log\Abstracts\AbstractLogger;
use Psr\Log\LogLevel;
use Stringable;

class SyslogLogger extends AbstractLogger
{
    private string $ident;
    private int $facility;

    public function __construct(?string $env = null, array $config = [])
    {
        parent::__construct($env, $config);

        $identValue = $config['ident'] ?? 'php';
        $this->ident = is_string($identValue) ? $identValue : 'php';

        $facilityValue = $config['facility'] ?? LOG_USER;
        $this->facility = is_int($facilityValue) ? $facilityValue : LOG_USER;

        openlog($this->ident, LOG_PID | LOG_ODELAY, $this->facility);
    }

    public function __destruct()
    {
        closelog();
    }

    public function smartLog(string|Stringable $message, array $context = []): void
    {
        $levelMap = match ($this->env) {
            'production', 'prod' => ['priority' => LOG_INFO, 'level' => 'info'],
            'staging', 'preprod' => ['priority' => LOG_NOTICE, 'level' => 'notice'],
            'testing', 'test' => ['priority' => LOG_WARNING, 'level' => 'warning'],
            default => ['priority' => LOG_DEBUG, 'level' => 'debug'],
        };

        $this->writeLog($levelMap['priority'], $levelMap['level'], $message, $context);
    }

    public function emergency(string|Stringable $message, array $context = []): void
    {
        $context = $this->enrichContext($context);
        $this->writeLog(LOG_EMERG, LogLevel::EMERGENCY, $message, $context);
    }

    public function alert(string|Stringable $message, array $context = []): void
    {
        $context = $this->enrichContext($context);
        $this->writeLog(LOG_ALERT, LogLevel::ALERT, $message, $context);
    }

    public function critical(string|Stringable $message, array $context = []): void
    {
        $context = $this->enrichContext($context);
        $this->writeLog(LOG_CRIT, LogLevel::CRITICAL, $message, $context);
    }

    public function error(string|Stringable $message, array $context = []): void
    {
        $context = $this->enrichContext($context);
        $this->writeLog(LOG_ERR, LogLevel::ERROR, $message, $context);
    }

    public function warning(string|Stringable $message, array $context = []): void
    {
        $context = $this->enrichContext($context);
        $this->writeLog(LOG_WARNING, LogLevel::WARNING, $message, $context);
    }

    public function notice(string|Stringable $message, array $context = []): void
    {
        $context = $this->enrichContext($context);
        $this->writeLog(LOG_NOTICE, LogLevel::NOTICE, $message, $context);
    }

    public function info(string|Stringable $message, array $context = []): void
    {
        $context = $this->enrichContext($context);
        $this->writeLog(LOG_INFO, LogLevel::INFO, $message, $context);
    }

    public function debug(string|Stringable $message, array $context = []): void
    {
        $context = $this->enrichContext($context);
        $this->writeLog(LOG_DEBUG, LogLevel::DEBUG, $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        $context = $this->enrichContext($context);
        $levelStr = is_string($level) ? $level : 'info';
        $priority = $this->levelToPriority($levelStr);
        $this->writeLog($priority, $levelStr, $message, $context);
    }

    /**
     * @param array<string|int, mixed> $context
     */
    private function writeLog(int $priority, string $level, string|Stringable $message, array $context): void
    {
        if (!$this->shouldLog($level)) {
            return;
        }

        $context = $this->enrichContext($context);
        $logLine = $this->formatMessage($level, $message, $context);
        syslog($priority, $logLine);
    }

    private function levelToPriority(string $level): int
    {
        return match ($level) {
            LogLevel::EMERGENCY => LOG_EMERG,
            LogLevel::ALERT => LOG_ALERT,
            LogLevel::CRITICAL => LOG_CRIT,
            LogLevel::ERROR => LOG_ERR,
            LogLevel::WARNING => LOG_WARNING,
            LogLevel::NOTICE => LOG_NOTICE,
            LogLevel::INFO => LOG_INFO,
            LogLevel::DEBUG => LOG_DEBUG,
            default => LOG_INFO,
        };
    }
}
