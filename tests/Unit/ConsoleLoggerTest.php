<?php

namespace MonkeysLegion\Log\Tests\Unit;

use MonkeysLegion\Log\Logger\ConsoleLogger;
use PHPUnit\Framework\TestCase;

class ConsoleLoggerTest extends TestCase
{
    /** @var resource */
    private $outputStream;
    private string $outputFile;

    protected function setUp(): void
    {
        $this->outputFile = sys_get_temp_dir() . '/console_output_' . uniqid() . '.txt';
        $this->outputStream = fopen($this->outputFile, 'w+');
    }

    protected function tearDown(): void
    {
        if (is_resource($this->outputStream)) {
            fclose($this->outputStream);
        }
        if (file_exists($this->outputFile)) {
            unlink($this->outputFile);
        }
    }

    private function getOutput(): string
    {
        if (is_resource($this->outputStream)) {
            rewind($this->outputStream);
            return stream_get_contents($this->outputStream);
        }
        return '';
    }

    public function testOutputsToConsole(): void
    {
        $logger = new ConsoleLogger('dev', [
            'colorize' => false,
            'output' => $this->outputStream,
        ]);
        $logger->info('Console test message');

        $output = $this->getOutput();

        $this->assertStringContainsString('Console test message', $output);
        $this->assertStringContainsString('INFO', $output);
    }

    public function testColorizedOutput(): void
    {
        $logger = new ConsoleLogger('dev', [
            'colorize' => true,
            'output' => $this->outputStream,
        ]);
        $logger->error('Error message');

        $output = $this->getOutput();

        $this->assertStringContainsString("\033[0;31m", $output); // Red color
        $this->assertStringContainsString("\033[0m", $output); // Reset
    }

    public function testNonColorizedOutput(): void
    {
        $logger = new ConsoleLogger('dev', [
            'colorize' => false,
            'output' => $this->outputStream,
        ]);
        $logger->warning('Warning message');

        $output = $this->getOutput();

        $this->assertStringNotContainsString("\033[", $output);
        $this->assertStringContainsString('WARNING', $output);
    }

    public function testSmartLogByEnvironment(): void
    {
        $prodLogger = new ConsoleLogger('production', [
            'colorize' => false,
            'output' => $this->outputStream,
        ]);
        $prodLogger->smartLog('Smart log');
        $prodOutput = $this->getOutput();

        $this->assertStringContainsString('INFO', $prodOutput);

        // Reset stream for next test
        ftruncate($this->outputStream, 0);
        rewind($this->outputStream);

        $testLogger = new ConsoleLogger('testing', [
            'colorize' => false,
            'output' => $this->outputStream,
        ]);
        $testLogger->smartLog('Smart log');
        $testOutput = $this->getOutput();

        $this->assertStringContainsString('WARNING', $testOutput);
    }

    public function testLogLevelColors(): void
    {
        $levels = [
            'emergency' => "\033[1;31m",
            'error' => "\033[0;31m",
            'warning' => "\033[0;33m",
            'notice' => "\033[0;36m",
            'info' => "\033[0;32m",
            'debug' => "\033[0;37m",
        ];

        foreach ($levels as $level => $expectedColor) {
            // Reset stream for each test
            ftruncate($this->outputStream, 0);
            rewind($this->outputStream);

            $logger = new ConsoleLogger('dev', [
                'colorize' => true,
                'output' => $this->outputStream,
            ]);
            $logger->$level('Test');
            $output = $this->getOutput();

            $this->assertStringContainsString($expectedColor, $output);
        }
    }

    public function testContextLogging(): void
    {
        $logger = new ConsoleLogger('dev', [
            'colorize' => false,
            'output' => $this->outputStream,
        ]);

        $context = ['user_id' => 123, 'action' => 'test'];
        $logger->info('Test with context', $context);

        $output = $this->getOutput();

        $this->assertStringContainsString('"user_id":123', $output);
        $this->assertStringContainsString('"action":"test"', $output);
    }

    public function testLogLevelFiltering(): void
    {
        $logger = new ConsoleLogger('dev', [
            'level' => 'error',
            'colorize' => false,
            'output' => $this->outputStream,
        ]);

        $logger->debug('Should not log');
        $logger->info('Should not log');
        $logger->error('Should log');

        $output = $this->getOutput();

        $this->assertStringNotContainsString('Should not log', $output);
        $this->assertStringContainsString('Should log', $output);
    }
}
