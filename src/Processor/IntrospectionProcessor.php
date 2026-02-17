<?php

namespace MonkeysLegion\Logger\Processor;

use MonkeysLegion\Logger\Contracts\ProcessorInterface;

/**
 * Adds call-site information (file, line, class, function) to the log record.
 *
 * Useful for quickly locating the source of a log entry, especially
 * when debugging 500 errors in controllers.
 */
class IntrospectionProcessor implements ProcessorInterface
{
    /** @var int Number of stack frames to skip (adjust if wrapped in more layers) */
    private int $skipFrames;

    /** @var list<string> Classes whose frames should be skipped */
    private array $skipClasses;

    /**
     * @param list<string> $skipClasses
     */
    public function __construct(int $skipFrames = 0, array $skipClasses = [])
    {
        $this->skipFrames  = $skipFrames;
        $this->skipClasses = $skipClasses;
    }

    public function __invoke(array $record): array
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 20);

        // Skip internal logger frames
        $skipPrefixes = array_merge(
            ['MonkeysLegion\\Logger\\'],
            $this->skipClasses,
        );

        $i = 0;
        foreach ($trace as $frame) {
            $class = $frame['class'] ?? '';

            $isInternal = false;
            foreach ($skipPrefixes as $prefix) {
                if (str_starts_with($class, $prefix)) {
                    $isInternal = true;
                    break;
                }
            }

            if (!$isInternal) {
                break;
            }
            $i++;
        }

        $i += $this->skipFrames;

        $callerFrame = $trace[$i] ?? [];
        $fileFrame   = $trace[$i - 1] ?? $trace[$i] ?? [];

        if (!isset($record['extra']) || !is_array($record['extra'])) {
            $record['extra'] = [];
        }

        $record['extra']['file']     = $fileFrame['file'] ?? 'unknown';
        $record['extra']['line']     = $fileFrame['line'] ?? 0;
        $record['extra']['class']    = $callerFrame['class'] ?? '';
        $record['extra']['function'] = $callerFrame['function'] ?? '';

        return $record;
    }
}
