<?php

namespace MonkeysLegion\Logger\Tests\Unit;

use MonkeysLegion\Logger\Formatter\LineFormatter;
use PHPUnit\Framework\TestCase;

class LineFormatterTest extends TestCase
{
    public function testBasicFormat(): void
    {
        $formatter = new LineFormatter('{level}: {message}');

        $result = $formatter->format('error', 'Something failed');

        $this->assertSame('ERROR: Something failed', $result);
    }

    public function testTimestampIncluded(): void
    {
        $formatter = new LineFormatter('[{timestamp}] {message}');

        $result = $formatter->format('info', 'Hello');

        // Should contain a date-like pattern
        $this->assertMatchesRegularExpression('/^\[\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $result);
    }

    public function testChannelAndEnvTokens(): void
    {
        $formatter = new LineFormatter('{channel}.{env} {level}: {message}');

        $result = $formatter->format('info', 'Test', channel: 'worker', env: 'production');

        $this->assertSame('worker.production INFO: Test', $result);
    }

    public function testContextJsonEncoded(): void
    {
        $formatter = new LineFormatter('{message} {context}');

        $result = $formatter->format('info', 'User login', ['user_id' => 42]);

        $this->assertStringContainsString('"user_id":42', $result);
    }

    public function testExtraJsonEncoded(): void
    {
        $formatter = new LineFormatter('{message} {extra}');

        $result = $formatter->format('info', 'Test', extra: ['uid' => 'abc123']);

        $this->assertStringContainsString('"uid":"abc123"', $result);
    }

    public function testPsr3PlaceholderInterpolation(): void
    {
        $formatter = new LineFormatter('{message}');

        $result = $formatter->format('info', 'User {user} performed {action}', [
            'user'   => 'john',
            'action' => 'login',
        ]);

        $this->assertStringContainsString('User john performed login', $result);
    }

    public function testInterpolationWithMissingKeyLeftAsIs(): void
    {
        $formatter = new LineFormatter('{message}');

        $result = $formatter->format('info', 'User {user} has role {role}', [
            'user' => 'john',
        ]);

        $this->assertStringContainsString('User john has role {role}', $result);
    }

    public function testInterpolationWithNonStringValues(): void
    {
        $formatter = new LineFormatter('{message}');

        $result = $formatter->format('info', 'Count: {count}, active: {active}', [
            'count'  => 42,
            'active' => true,
        ]);

        $this->assertStringContainsString('Count: 42', $result);
    }

    public function testInterpolationWithDateTimeValue(): void
    {
        $formatter = new LineFormatter('{message}');
        $date = new \DateTimeImmutable('2024-01-15T10:30:00+00:00');

        $result = $formatter->format('info', 'Created at {created_at}', [
            'created_at' => $date,
        ]);

        $this->assertStringContainsString('2024-01-15T10:30:00', $result);
    }

    public function testInterpolationWithArrayValue(): void
    {
        $formatter = new LineFormatter('{message}');

        $result = $formatter->format('info', 'Roles: {roles}', [
            'roles' => ['admin', 'user'],
        ]);

        $this->assertStringContainsString('["admin","user"]', $result);
    }

    public function testEmptyContextProducesEmptyString(): void
    {
        $formatter = new LineFormatter('{message}|{context}|');

        $result = $formatter->format('info', 'Test', []);

        $this->assertSame('Test||', $result);
    }

    public function testNoPlaceholdersFastPath(): void
    {
        $formatter = new LineFormatter('{message}');

        $result = $formatter->format('info', 'No placeholders here');

        $this->assertSame('No placeholders here', $result);
    }
}
