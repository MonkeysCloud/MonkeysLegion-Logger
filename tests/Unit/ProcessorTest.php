<?php

namespace MonkeysLegion\Logger\Tests\Unit;

use MonkeysLegion\Logger\Processor\IntrospectionProcessor;
use MonkeysLegion\Logger\Processor\MemoryUsageProcessor;
use MonkeysLegion\Logger\Processor\UidProcessor;
use PHPUnit\Framework\TestCase;

class ProcessorTest extends TestCase
{
    public function testIntrospectionProcessorAddsFileAndLine(): void
    {
        $processor = new IntrospectionProcessor();
        $record = ['level' => 'info', 'message' => 'test', 'context' => [], 'extra' => []];

        $result = $processor($record);

        $this->assertArrayHasKey('file', $result['extra']);
        $this->assertArrayHasKey('line', $result['extra']);
        $this->assertArrayHasKey('class', $result['extra']);
        $this->assertArrayHasKey('function', $result['extra']);
    }

    public function testIntrospectionProcessorSkipsLoggerFrames(): void
    {
        $processor = new IntrospectionProcessor();
        $record = ['level' => 'info', 'message' => 'test', 'context' => [], 'extra' => []];

        $result = $processor($record);

        // The file should be a .php file, not 'unknown'
        $this->assertStringEndsWith('.php', $result['extra']['file']);
        // Should NOT reference internal MonkeysLegion\Logger classes by namespace
        $this->assertStringNotContainsString('MonkeysLegion\\Logger\\', $result['extra']['class']);
    }

    public function testMemoryUsageProcessorAddsMemoryInfo(): void
    {
        $processor = new MemoryUsageProcessor();
        $record = ['level' => 'info', 'message' => 'test', 'context' => [], 'extra' => []];

        $result = $processor($record);

        $this->assertArrayHasKey('memory_usage', $result['extra']);
        $this->assertArrayHasKey('memory_peak', $result['extra']);
        $this->assertMatchesRegularExpression('/^\d+(\.\d+)?\s+(B|KB|MB|GB)$/', $result['extra']['memory_usage']);
        $this->assertMatchesRegularExpression('/^\d+(\.\d+)?\s+(B|KB|MB|GB)$/', $result['extra']['memory_peak']);
    }

    public function testUidProcessorAddsUid(): void
    {
        $processor = new UidProcessor(8);
        $record = ['level' => 'info', 'message' => 'test', 'context' => [], 'extra' => []];

        $result = $processor($record);

        $this->assertArrayHasKey('uid', $result['extra']);
        $this->assertSame(8, strlen($result['extra']['uid']));
    }

    public function testUidProcessorStableAcrossCalls(): void
    {
        $processor = new UidProcessor();
        $record = ['level' => 'info', 'message' => 'test', 'context' => [], 'extra' => []];

        $result1 = $processor($record);
        $result2 = $processor($record);

        $this->assertSame($result1['extra']['uid'], $result2['extra']['uid']);
    }

    public function testUidProcessorReset(): void
    {
        $processor = new UidProcessor();
        $uid1 = $processor->getUid();

        $processor->reset();
        $uid2 = $processor->getUid();

        $this->assertNotSame($uid1, $uid2);
    }

    public function testUidProcessorGetUid(): void
    {
        $processor = new UidProcessor(12);

        $this->assertSame(12, strlen($processor->getUid()));
    }

    public function testProcessorCreatesExtraIfMissing(): void
    {
        $processor = new MemoryUsageProcessor();
        $record = ['level' => 'info', 'message' => 'test', 'context' => []];

        $result = $processor($record);

        $this->assertArrayHasKey('extra', $result);
        $this->assertArrayHasKey('memory_usage', $result['extra']);
    }
}
