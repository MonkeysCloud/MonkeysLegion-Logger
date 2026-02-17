<?php

namespace MonkeysLegion\Logger\Tests\Unit;

use MonkeysLegion\Logger\Logger\ConsoleLogger;
use PHPUnit\Framework\TestCase;

class ConsoleLoggerTest extends TestCase
{
    /** @var resource */
    private $outputStream;
    /** @var resource */
    private $errorStream;
    private string $outputFile;
    private string $errorFile;

    protected function setUp(): void
    {
        $this->outputFile = sys_get_temp_dir() . '/console_output_' . uniqid() . '.txt';
        $this->outputStream = fopen($this->outputFile, 'w+');

        $this->errorFile = sys_get_temp_dir() . '/console_error_' . uniqid() . '.txt';
        $this->errorStream = fopen($this->errorFile, 'w+');
    }

    protected function tearDown(): void
    {
        if (is_resource($this->outputStream)) {
            fclose($this->outputStream);
        }
        if (is_resource($this->errorStream)) {
            fclose($this->errorStream);
        }
        if (file_exists($this->outputFile)) {
            unlink($this->outputFile);
        }
        if (file_exists($this->errorFile)) {
            unlink($this->errorFile);
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

    private function getErrorOutput(): string
    {
        if (is_resource($this->errorStream)) {
            rewind($this->errorStream);
            return stream_get_contents($this->errorStream);
        }
        return '';
    }

    private function resetStreams(): void
    {
        ftruncate($this->outputStream, 0);
        rewind($this->outputStream);
        ftruncate($this->errorStream, 0);
        rewind($this->errorStream);
    }

    /**
     * @return array<string, mixed>
     */
    private function loggerConfig(bool $colorize = false): array
    {
        return [
            'colorize'     => $colorize,
            'output'       => $this->outputStream,
            'error_output' => $this->errorStream,
        ];
    }

    public function testOutputsToConsole(): void
    {
        $logger = new ConsoleLogger('dev', $this->loggerConfig());
        $logger->info('Console test message');

        $output = $this->getOutput();

        $this->assertStringContainsString('Console test message', $output);
        $this->assertStringContainsString('INFO', $output);
    }

    public function testColorizedOutput(): void
    {
        $logger = new ConsoleLogger('dev', $this->loggerConfig(colorize: true));
        $logger->error('Error message');

        // Error goes to stderr
        $output = $this->getErrorOutput();

        $this->assertStringContainsString("\033[0;31m", $output); // Red color
        $this->assertStringContainsString("\033[0m", $output); // Reset
    }

    public function testNonColorizedOutput(): void
    {
        $logger = new ConsoleLogger('dev', $this->loggerConfig());
        $logger->warning('Warning message');

        $output = $this->getOutput();

        $this->assertStringNotContainsString("\033[", $output);
        $this->assertStringContainsString('WARNING', $output);
    }

    public function testSmartLogByEnvironment(): void
    {
        $prodLogger = new ConsoleLogger('production', $this->loggerConfig());
        $prodLogger->smartLog('Smart log');
        $prodOutput = $this->getOutput();

        $this->assertStringContainsString('INFO', $prodOutput);

        $this->resetStreams();

        $testLogger = new ConsoleLogger('testing', $this->loggerConfig());
        $testLogger->smartLog('Smart log');
        $testOutput = $this->getOutput();

        $this->assertStringContainsString('WARNING', $testOutput);
    }

    public function testLogLevelColors(): void
    {
        // stdout levels
        $stdoutLevels = [
            'warning' => "\033[0;33m",
            'notice'  => "\033[0;36m",
            'info'    => "\033[0;32m",
            'debug'   => "\033[0;37m",
        ];

        foreach ($stdoutLevels as $level => $expectedColor) {
            $this->resetStreams();

            $logger = new ConsoleLogger('dev', $this->loggerConfig(colorize: true));
            $logger->$level('Test');
            $output = $this->getOutput();

            $this->assertStringContainsString($expectedColor, $output, "Expected color for {$level}");
        }

        // stderr levels (error and above)
        $stderrLevels = [
            'emergency' => "\033[1;31m",
            'error'     => "\033[0;31m",
        ];

        foreach ($stderrLevels as $level => $expectedColor) {
            $this->resetStreams();

            $logger = new ConsoleLogger('dev', $this->loggerConfig(colorize: true));
            $logger->$level('Test');
            $output = $this->getErrorOutput();

            $this->assertStringContainsString($expectedColor, $output, "Expected color for {$level}");
        }
    }

    public function testContextLogging(): void
    {
        $logger = new ConsoleLogger('dev', $this->loggerConfig());

        $context = ['user_id' => 123, 'action' => 'test'];
        $logger->info('Test with context', $context);

        $output = $this->getOutput();

        $this->assertStringContainsString('"user_id":123', $output);
        $this->assertStringContainsString('"action":"test"', $output);
    }

    public function testLogLevelFiltering(): void
    {
        $config = array_merge($this->loggerConfig(), ['level' => 'error']);
        $logger = new ConsoleLogger('dev', $config);

        $logger->debug('Should not log');
        $logger->info('Should not log');
        $logger->error('Should log');

        $stdoutOutput = $this->getOutput();
        $stderrOutput = $this->getErrorOutput();

        $this->assertStringNotContainsString('Should not log', $stdoutOutput);
        $this->assertStringNotContainsString('Should not log', $stderrOutput);
        $this->assertStringContainsString('Should log', $stderrOutput);
    }

    public function testErrorRoutesToStderr(): void
    {
        $logger = new ConsoleLogger('dev', $this->loggerConfig());

        $logger->info('Info goes to stdout');
        $logger->error('Error goes to stderr');
        $logger->critical('Critical goes to stderr');

        $stdout = $this->getOutput();
        $stderr = $this->getErrorOutput();

        $this->assertStringContainsString('Info goes to stdout', $stdout);
        $this->assertStringContainsString('Error goes to stderr', $stderr);
        $this->assertStringContainsString('Critical goes to stderr', $stderr);
    }
}
