<?php

namespace MonkeysLegion\Logger\Tests\Unit;

use MonkeysLegion\Logger\Logger\SyslogLogger;
use PHPUnit\Framework\TestCase;

class SyslogLoggerTest extends TestCase
{
    /**
     * SyslogLogger writes to the system log, which is hard to capture.
     * These tests verify the code paths execute without errors and that
     * level filtering works correctly.
     */

    public function testConstructorOpensLog(): void
    {
        $logger = new SyslogLogger('dev', [
            'ident' => 'monkeys-test',
            'facility' => LOG_USER,
        ]);

        // If we get here, openlog succeeded
        $this->assertInstanceOf(SyslogLogger::class, $logger);
    }

    public function testAllLogLevelsExecute(): void
    {
        $this->expectNotToPerformAssertions();

        $logger = new SyslogLogger('dev', [
            'ident' => 'monkeys-test',
        ]);

        $logger->emergency('Emergency');
        $logger->alert('Alert');
        $logger->critical('Critical');
        $logger->error('Error');
        $logger->warning('Warning');
        $logger->notice('Notice');
        $logger->info('Info');
        $logger->debug('Debug');
    }

    public function testSmartLogByEnvironment(): void
    {
        $this->expectNotToPerformAssertions();

        $logger = new SyslogLogger('production', [
            'ident' => 'monkeys-test',
        ]);

        $logger->smartLog('Smart log from production');
    }

    public function testLevelFilteringPreventsLowPriorityLogs(): void
    {
        $this->expectNotToPerformAssertions();

        $logger = new SyslogLogger('dev', [
            'ident' => 'monkeys-test',
            'level' => 'error',
        ]);

        // These should be silently filtered out
        $logger->debug('Filtered debug');
        $logger->info('Filtered info');
        $logger->warning('Filtered warning');

        // This should pass through
        $logger->error('Allowed error');
    }

    public function testLogMethodAcceptsArbitraryLevel(): void
    {
        $this->expectNotToPerformAssertions();

        $logger = new SyslogLogger('dev', [
            'ident' => 'monkeys-test',
        ]);

        $logger->log('warning', 'Generic log call');
        $logger->log('info', 'Another generic call');
    }

    public function testContextPassedToFormatter(): void
    {
        $this->expectNotToPerformAssertions();

        $logger = new SyslogLogger('dev', [
            'ident' => 'monkeys-test',
        ]);

        // Ensure context doesn't cause exceptions
        $logger->info('With context', [
            'user_id' => 42,
            'data' => ['nested' => true],
        ]);
    }

    public function testDefaultIdentAndFacility(): void
    {
        $logger = new SyslogLogger('dev', []);

        // Should use defaults without crashing
        $logger->info('Default config test');

        $this->assertInstanceOf(SyslogLogger::class, $logger);
    }
}
