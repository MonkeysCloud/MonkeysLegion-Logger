<?php

namespace MonkeysLegion\Logger\Formatter;

use MonkeysLegion\Logger\Contracts\FormatterInterface;
use Stringable;

/**
 * Structured JSON formatter — one JSON object per log line.
 *
 * Ideal for log aggregation systems (ELK, Datadog, CloudWatch, Loki).
 */
class JsonFormatter implements FormatterInterface
{
    private bool $prettyPrint;

    public function __construct(
        bool $prettyPrint = false,
    ) {
        $this->prettyPrint        = $prettyPrint;
    }

    public function format(
        string          $level,
        string|Stringable $message,
        array           $context = [],
        array           $extra = [],
        string          $channel = 'app',
        string          $env = 'dev',
    ): string {
        $record = [
            'timestamp' => (new \DateTimeImmutable())->format('Y-m-d\TH:i:s.uP'),
            'level'     => strtoupper($level),
            'channel'   => $channel,
            'env'       => $env,
            'message'   => (string) $message,
        ];

        if (!empty($context)) {
            $record['context'] = $context;
        }

        if (!empty($extra)) {
            $record['extra'] = $extra;
        }

        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
        if ($this->prettyPrint) {
            $flags |= JSON_PRETTY_PRINT;
        }

        $json = json_encode($record, $flags);

        return is_string($json) ? $json : '{"error":"json_encode failed"}';
    }
}
