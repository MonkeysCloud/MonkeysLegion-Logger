<?php

namespace MonkeysLegion\Logger\Formatter;

use MonkeysLegion\Logger\Contracts\FormatterInterface;
use Stringable;

/**
 * Human-readable single-line formatter with PSR-3 placeholder interpolation.
 *
 * Supported tokens: {timestamp}, {env}, {level}, {channel}, {message}, {context}, {extra}
 */
class LineFormatter implements FormatterInterface
{
    private string $format;
    private string $dateFormat;

    public function __construct(
        string $format = '[{timestamp}] [{channel}.{env}] {level}: {message} {context}',
        string $dateFormat = 'Y-m-d\TH:i:s.uP',
    ) {
        $this->format     = $format;
        $this->dateFormat = $dateFormat;
    }

    public function format(
        string          $level,
        string|Stringable $message,
        array           $context = [],
        array           $extra = [],
        string          $channel = 'app',
        string          $env = 'dev',
    ): string {
        $timestamp = (new \DateTimeImmutable())->format($this->dateFormat);

        // PSR-3 message interpolation: replace {key} with context values
        $interpolated = $this->interpolate((string) $message, $context);

        $contextJson = !empty($context) ? json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '';
        $extraJson   = !empty($extra)   ? json_encode($extra,   JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '';

        return str_replace(
            ['{timestamp}', '{env}', '{level}', '{channel}', '{message}', '{context}', '{extra}'],
            [$timestamp, $env, strtoupper($level), $channel, $interpolated, is_string($contextJson) ? $contextJson : '', is_string($extraJson) ? $extraJson : ''],
            $this->format,
        );
    }

    /**
     * PSR-3 compliant message interpolation.
     *
     * Replaces `{key}` placeholders in the message with values from context.
     *
     * @param array<string|int, mixed> $context
     */
    private function interpolate(string $message, array $context): string
    {
        if (!str_contains($message, '{')) {
            return $message;
        }

        $replacements = [];
        foreach ($context as $key => $value) {
            if (!is_string($key)) {
                continue;
            }
            $placeholder = '{' . $key . '}';
            if (!str_contains($message, $placeholder)) {
                continue;
            }
            if ($value instanceof \DateTimeInterface) {
                $replacements[$placeholder] = $value->format(\DateTimeInterface::RFC3339);
            } elseif ($value instanceof Stringable) {
                $replacements[$placeholder] = (string) $value;
            } elseif (is_scalar($value) || $value === null) {
                $replacements[$placeholder] = (string) $value;
            } else {
                $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                $replacements[$placeholder] = is_string($encoded) ? $encoded : '[unserializable]';
            }
        }

        return strtr($message, $replacements);
    }
}
