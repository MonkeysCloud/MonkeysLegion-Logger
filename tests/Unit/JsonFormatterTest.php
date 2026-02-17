<?php

namespace MonkeysLegion\Logger\Tests\Unit;

use MonkeysLegion\Logger\Formatter\JsonFormatter;
use PHPUnit\Framework\TestCase;

class JsonFormatterTest extends TestCase
{
    public function testOutputIsValidJson(): void
    {
        $formatter = new JsonFormatter();

        $result = $formatter->format('error', 'Something failed', ['key' => 'value']);

        $decoded = json_decode($result, true);
        $this->assertIsArray($decoded);
    }

    public function testRequiredKeysPresent(): void
    {
        $formatter = new JsonFormatter();

        $result = $formatter->format('error', 'Test message');
        $decoded = json_decode($result, true);

        $this->assertArrayHasKey('timestamp', $decoded);
        $this->assertArrayHasKey('level', $decoded);
        $this->assertArrayHasKey('channel', $decoded);
        $this->assertArrayHasKey('env', $decoded);
        $this->assertArrayHasKey('message', $decoded);
    }

    public function testLevelIsUppercase(): void
    {
        $formatter = new JsonFormatter();

        $result = $formatter->format('error', 'Test');
        $decoded = json_decode($result, true);

        $this->assertSame('ERROR', $decoded['level']);
    }

    public function testContextIncludedWhenNotEmpty(): void
    {
        $formatter = new JsonFormatter();

        $result = $formatter->format('info', 'Test', ['user_id' => 42]);
        $decoded = json_decode($result, true);

        $this->assertArrayHasKey('context', $decoded);
        $this->assertSame(42, $decoded['context']['user_id']);
    }

    public function testContextOmittedWhenEmpty(): void
    {
        $formatter = new JsonFormatter();

        $result = $formatter->format('info', 'Test', []);
        $decoded = json_decode($result, true);

        $this->assertArrayNotHasKey('context', $decoded);
    }

    public function testExtraIncluded(): void
    {
        $formatter = new JsonFormatter();

        $result = $formatter->format('info', 'Test', [], ['uid' => 'abc']);
        $decoded = json_decode($result, true);

        $this->assertArrayHasKey('extra', $decoded);
        $this->assertSame('abc', $decoded['extra']['uid']);
    }

    public function testExtraOmittedWhenEmpty(): void
    {
        $formatter = new JsonFormatter();

        $result = $formatter->format('info', 'Test');
        $decoded = json_decode($result, true);

        $this->assertArrayNotHasKey('extra', $decoded);
    }

    public function testChannelAndEnv(): void
    {
        $formatter = new JsonFormatter();

        $result = $formatter->format('info', 'Test', channel: 'worker', env: 'production');
        $decoded = json_decode($result, true);

        $this->assertSame('worker', $decoded['channel']);
        $this->assertSame('production', $decoded['env']);
    }

    public function testPrettyPrint(): void
    {
        $formatter = new JsonFormatter(prettyPrint: true);

        $result = $formatter->format('info', 'Test');

        // Pretty-printed JSON should contain newlines
        $this->assertStringContainsString("\n", $result);
    }

    public function testSpecialCharactersEncoded(): void
    {
        $formatter = new JsonFormatter();

        $result = $formatter->format('info', 'Special: "quotes" & <tags>', [
            'url' => 'https://example.com/path?a=1&b=2',
        ]);

        $decoded = json_decode($result, true);
        $this->assertIsArray($decoded);
        $this->assertSame('https://example.com/path?a=1&b=2', $decoded['context']['url']);
    }
}
