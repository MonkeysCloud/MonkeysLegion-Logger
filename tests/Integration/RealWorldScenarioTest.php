<?php

namespace MonkeysLegion\Log\Tests\Integration;

use MonkeysLegion\Log\Factory\LoggerFactory;
use PHPUnit\Framework\TestCase;

class RealWorldScenarioTest extends TestCase
{
    private string $logDir;

    protected function setUp(): void
    {
        $this->logDir = sys_get_temp_dir() . '/integration_test_' . uniqid();
        mkdir($this->logDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->recursiveRemoveDirectory($this->logDir);
    }

    public function testProductionConfiguration(): void
    {
        $config = [
            'default' => 'stack',
            'channels' => [
                'stack' => [
                    'driver' => 'stack',
                    'channels' => ['daily', 'syslog'],
                ],
                'daily' => [
                    'driver' => 'file',
                    'path' => $this->logDir . '/app-{date}.log',
                    'level' => 'info',
                    'date_format' => 'Y-m-d',
                ],
                'syslog' => [
                    'driver' => 'syslog',
                    'level' => 'warning',
                    'ident' => 'test_app',
                ],
            ],
        ];

        $factory = new LoggerFactory($config, 'production');
        $logger = $factory->make();

        $logger->debug('This should not log');
        $logger->info('This should log');
        $logger->error('This is an error');

        $expectedPath = $this->logDir . '/app-' . date('Y-m-d') . '.log';
        $this->assertFileExists($expectedPath);

        $content = file_get_contents($expectedPath);
        $this->assertStringNotContainsString('This should not log', $content);
        $this->assertStringContainsString('This should log', $content);
        $this->assertStringContainsString('This is an error', $content);
    }

    public function testDevelopmentConfiguration(): void
    {
        $config = [
            'default' => 'stack',
            'channels' => [
                'stack' => [
                    'driver' => 'stack',
                    'channels' => ['single'],
                ],
                'single' => [
                    'driver' => 'file',
                    'path' => $this->logDir . '/dev.log',
                    'level' => 'debug',
                ],
            ],
        ];

        $factory = new LoggerFactory($config, 'development');
        $logger = $factory->make();

        $logger->debug('Debug message');
        $logger->info('Info message');

        $content = file_get_contents($this->logDir . '/dev.log');
        $this->assertStringContainsString('Debug message', $content);
        $this->assertStringContainsString('Info message', $content);
    }

    public function testHighVolumeLogging(): void
    {
        $config = [
            'channels' => [
                'file' => [
                    'driver' => 'file',
                    'path' => $this->logDir . '/volume.log',
                ],
            ],
        ];

        $factory = new LoggerFactory($config);
        $logger = $factory->make('file');

        $iterations = 1000;
        for ($i = 0; $i < $iterations; $i++) {
            $logger->info("Message {$i}", ['iteration' => $i]);
        }

        $content = file_get_contents($this->logDir . '/volume.log');
        $lineCount = substr_count($content, "\n");

        $this->assertGreaterThanOrEqual($iterations, $lineCount);
    }

    public function testComplexContextLogging(): void
    {
        $config = [
            'channels' => [
                'file' => [
                    'driver' => 'file',
                    'path' => $this->logDir . '/context.log',
                ],
            ],
        ];

        $factory = new LoggerFactory($config);
        $logger = $factory->make('file');

        $complexContext = [
            'user' => [
                'id' => 123,
                'name' => 'John Doe',
                'roles' => ['admin', 'user'],
            ],
            'request' => [
                'method' => 'POST',
                'uri' => '/api/users',
                'ip' => '192.168.1.1',
            ],
            'metadata' => [
                'timestamp' => time(),
                'session_id' => uniqid(),
            ],
        ];

        $logger->info('Complex context test', $complexContext);

        $content = file_get_contents($this->logDir . '/context.log');
        $this->assertStringContainsString('"id":123', $content);
        $this->assertStringContainsString('"name":"John Doe"', $content);
        $this->assertStringContainsString('"method":"POST"', $content);
    }

    private function recursiveRemoveDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->recursiveRemoveDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
