<?php

declare(strict_types=1);

namespace PhelTest\Unit\Run\Domain\Config;

use Phel\Config\PhelConfig;
use Phel\Run\Domain\Config\ConfigDiagnostics;
use Phel\Run\Domain\Config\ConfigIssueLevel;
use PHPUnit\Framework\TestCase;

use function bin2hex;
use function mkdir;
use function random_bytes;
use function rmdir;
use function sys_get_temp_dir;

final class ConfigDiagnosticsTest extends TestCase
{
    private ConfigDiagnostics $diagnostics;

    private string $root;

    protected function setUp(): void
    {
        $this->diagnostics = new ConfigDiagnostics();
        $this->root = sys_get_temp_dir() . '/phel-diag-' . bin2hex(random_bytes(6));
        mkdir($this->root, 0755, true);
    }

    protected function tearDown(): void
    {
        @rmdir($this->root . '/src');
        @rmdir($this->root . '/tests');
        @rmdir($this->root);
    }

    public function test_existing_relative_dirs_produce_no_issues(): void
    {
        mkdir($this->root . '/src');
        mkdir($this->root . '/tests');

        $issues = $this->diagnostics->analyze([
            PhelConfig::SRC_DIRS => ['src'],
            PhelConfig::TEST_DIRS => ['tests'],
            PhelConfig::VENDOR_DIR => 'vendor',
            PhelConfig::OPTIMIZATION_LEVEL => 2,
        ], $this->root);

        self::assertSame([], $issues);
    }

    public function test_missing_keys_produce_no_issues(): void
    {
        self::assertSame([], $this->diagnostics->analyze([], $this->root));
    }

    public function test_absolute_src_dir_reports_an_error_and_not_a_missing_warning(): void
    {
        $issues = $this->diagnostics->analyze([
            PhelConfig::SRC_DIRS => ['/absolute/src'],
        ], $this->root);

        // Absolute dirs are flagged once (relative-path error), never doubled
        // up with a "does not exist" warning.
        self::assertCount(1, $issues);
        self::assertSame(ConfigIssueLevel::Error, $issues[0]->level);
        self::assertStringContainsString('should be relative', $issues[0]->message);
    }

    public function test_missing_relative_source_dir_warns(): void
    {
        $issues = $this->diagnostics->analyze([
            PhelConfig::SRC_DIRS => ['does-not-exist'],
        ], $this->root);

        self::assertCount(1, $issues);
        self::assertSame(ConfigIssueLevel::Warning, $issues[0]->level);
        self::assertStringContainsString("Source directory 'does-not-exist' does not exist", $issues[0]->message);
    }

    public function test_empty_source_dirs_warn(): void
    {
        $issues = $this->diagnostics->analyze([
            PhelConfig::SRC_DIRS => [],
        ], $this->root);

        self::assertCount(1, $issues);
        self::assertSame(ConfigIssueLevel::Warning, $issues[0]->level);
        self::assertStringContainsString('nothing will be compiled', $issues[0]->message);
    }

    public function test_unknown_optimization_level_warns(): void
    {
        mkdir($this->root . '/src');

        $issues = $this->diagnostics->analyze([
            PhelConfig::SRC_DIRS => ['src'],
            PhelConfig::OPTIMIZATION_LEVEL => 5,
        ], $this->root);

        self::assertCount(1, $issues);
        self::assertSame(ConfigIssueLevel::Warning, $issues[0]->level);
        self::assertStringContainsString('optimization level 5', $issues[0]->message);
    }
}
