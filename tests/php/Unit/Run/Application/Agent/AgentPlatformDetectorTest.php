<?php

declare(strict_types=1);

namespace PhelTest\Unit\Run\Application\Agent;

use Phel\Run\Application\Agent\AgentPlatformDetector;
use Phel\Run\Domain\Agent\AgentPlatformRegistry;
use PHPUnit\Framework\TestCase;

use function in_array;

final class AgentPlatformDetectorTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/phel-detector-test-' . uniqid();
        mkdir($this->projectDir, 0o755, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->projectDir . '/{,.}*', GLOB_BRACE) ?: [] as $path) {
            if (is_file($path)) {
                unlink($path);
            } elseif (is_dir($path) && !in_array(basename($path), ['.', '..'], true)) {
                rmdir($path);
            }
        }

        rmdir($this->projectDir);
    }

    public function test_detects_nothing_in_an_empty_project(): void
    {
        self::assertSame([], $this->detect());
    }

    public function test_detects_a_directory_signal(): void
    {
        mkdir($this->projectDir . '/.claude');

        self::assertSame(['claude'], $this->detect());
    }

    public function test_detects_a_file_signal(): void
    {
        file_put_contents($this->projectDir . '/AGENTS.md', '');

        self::assertSame(['codex'], $this->detect());
    }

    public function test_returns_keys_in_registry_order(): void
    {
        // Created out of order; detection must follow the registry's key order.
        file_put_contents($this->projectDir . '/CONVENTIONS.md', '');
        mkdir($this->projectDir . '/.cursor');
        mkdir($this->projectDir . '/.claude');

        self::assertSame(['claude', 'cursor', 'aider'], $this->detect());
    }

    /**
     * @return list<string>
     */
    private function detect(): array
    {
        return new AgentPlatformDetector(new AgentPlatformRegistry())->detect($this->projectDir);
    }
}
