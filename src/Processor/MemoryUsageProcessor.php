<?php

namespace MonkeysLegion\Logger\Processor;

use MonkeysLegion\Logger\Contracts\ProcessorInterface;

/**
 * Adds current and peak memory usage to the log record.
 */
class MemoryUsageProcessor implements ProcessorInterface
{
    private bool $realUsage;

    public function __construct(bool $realUsage = true)
    {
        $this->realUsage = $realUsage;
    }

    public function __invoke(array $record): array
    {
        if (!isset($record['extra']) || !is_array($record['extra'])) {
            $record['extra'] = [];
        }

        $record['extra']['memory_usage'] = $this->formatBytes(memory_get_usage($this->realUsage));
        $record['extra']['memory_peak']  = $this->formatBytes(memory_get_peak_usage($this->realUsage));

        return $record;
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];

        $i = 0;
        $value = (float) $bytes;
        while ($value >= 1024 && $i < count($units) - 1) {
            $value /= 1024;
            $i++;
        }

        return round($value, 2) . ' ' . $units[$i];
    }
}
