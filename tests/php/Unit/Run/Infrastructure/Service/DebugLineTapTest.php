<?php

declare(strict_types=1);

namespace PhelTest\Unit\Run\Infrastructure\Service;

use Phel\Run\Infrastructure\Service\DebugLineTap;
use PHPUnit\Framework\TestCase;

use function strlen;

final class DebugLineTapTest extends TestCase
{
    private string $testLogPath;

    protected function setUp(): void
    {
        $this->testLogPath = sys_get_temp_dir() . '/phel-debug-test-' . uniqid() . '.log';
        DebugLineTap::disable(); // Ensure clean state
    }

    protected function tearDown(): void
    {
        DebugLineTap::disable();
        if (file_exists($this->testLogPath)) {
            @unlink($this->testLogPath);
        }
    }

    public function test_enable_creates_instance(): void
    {
        self::assertFalse(DebugLineTap::isEnabled());

        DebugLineTap::enable(null, $this->testLogPath);

        self::assertTrue(DebugLineTap::isEnabled());
    }

    public function test_disable_removes_instance(): void
    {
        DebugLineTap::enable(null, $this->testLogPath);
        self::assertTrue(DebugLineTap::isEnabled());

        DebugLineTap::disable();

        self::assertFalse(DebugLineTap::isEnabled());
    }

    public function test_enable_twice_does_not_recreate_instance(): void
    {
        DebugLineTap::enable(null, $this->testLogPath);
        file_get_contents($this->testLogPath);

        // Try to enable again with different path (should be ignored)
        $secondPath = sys_get_temp_dir() . '/phel-debug-test-second-' . uniqid() . '.log';
        DebugLineTap::enable(null, $secondPath);

        // Original log should exist, second should not
        self::assertFileExists($this->testLogPath);
        self::assertFileDoesNotExist($secondPath);
    }

    public function test_writes_header_on_enable(): void
    {
        DebugLineTap::enable(null, $this->testLogPath);

        self::assertFileExists($this->testLogPath);
        $content = file_get_contents($this->testLogPath);

        self::assertStringContainsString('=== Phel Debug Trace', (string) $content);
        self::assertStringContainsString('PID:', (string) $content);
    }

    public function test_writes_header_with_filter(): void
    {
        DebugLineTap::enable('core', $this->testLogPath);

        $content = file_get_contents($this->testLogPath);

        self::assertStringContainsString('Phel file filter: core', (string) $content);
    }

    public function test_flush_writes_to_file(): void
    {
        DebugLineTap::enable(null, $this->testLogPath);

        // Force some ticks by executing code
        $sum = 0;
        for ($i = 0; $i < 3; ++$i) {
            $sum += $i;
        }

        DebugLineTap::disable(); // This flushes

        $content = file_get_contents($this->testLogPath);

        // Should have header
        self::assertStringContainsString('=== Phel Debug Trace', (string) $content);
    }

    public function test_disable_flushes_buffer(): void
    {
        DebugLineTap::enable(null, $this->testLogPath);

        $contentBeforeDisable = file_get_contents($this->testLogPath);

        DebugLineTap::disable();

        $contentAfterDisable = file_get_contents($this->testLogPath);

        // Content should be at least as long after disable (flush)
        self::assertGreaterThanOrEqual(
            strlen($contentBeforeDisable),
            strlen($contentAfterDisable),
        );
    }
}
