<?php

namespace MonkeysLegion\Log\Tests\Unit;

use MonkeysLegion\Log\Logger\NullLogger;
use PHPUnit\Framework\TestCase;

class NullLoggerTest extends TestCase
{
    public function testDoesNothing(): void
    {
        $logger = new NullLogger('dev');

        // Should not throw any exceptions or produce output
        ob_start();

        $logger->emergency('Emergency');
        $logger->alert('Alert');
        $logger->critical('Critical');
        $logger->error('Error');
        $logger->warning('Warning');
        $logger->notice('Notice');
        $logger->info('Info');
        $logger->debug('Debug');
        $logger->smartLog('Smart');
        $logger->log('custom', 'Custom level');

        $output = ob_get_clean();

        $this->assertEmpty($output);
    }

    public function testAcceptsAnyContext(): void
    {
        $logger = new NullLogger('dev');

        $this->expectNotToPerformAssertions();

        $logger->info('Test', ['complex' => ['nested' => ['data' => true]]]);
    }
}
