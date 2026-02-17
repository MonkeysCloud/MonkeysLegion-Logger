<?php

namespace MonkeysLegion\Logger\Contracts;

/**
 * Enriches a log record with additional data before formatting.
 */
interface ProcessorInterface
{
    /**
     * @param  array<string, mixed> $record  Keys: level, message, context, extra, channel
     * @return array<string, mixed>          The enriched record
     */
    public function __invoke(array $record): array;
}
