<?php

declare(strict_types=1);

namespace PhelTest\Unit\Run\Domain\Config;

use Phel\Config\PhelConfig;
use Phel\Run\Domain\Config\ConfigDiagnostics;
use Phel\Run\Domain\Config\ConfigIssueLevel;
use PHPUnit\Framework\TestCase;

final class ConfigDiagnosticsTest extends TestCase
{
    private ConfigDiagnostics $diagnostics;

    protected function setUp(): void
    {
        $this->diagnostics = new ConfigDiagnostics();
    }

    public function test_relative_dirs_produce_no_issues(): void
    {
        $issues = $this->diagnostics->analyze([
            PhelConfig::SRC_DIRS => ['src'],
            PhelConfig::TEST_DIRS => ['tests'],
            PhelConfig::VENDOR_DIR => 'vendor',
        ]);

        self::assertSame([], $issues);
    }

    public function test_missing_keys_produce_no_issues(): void
    {
        self::assertSame([], $this->diagnostics->analyze([]));
    }

    public function test_absolute_src_dir_is_reported_as_an_error(): void
    {
        $issues = $this->diagnostics->analyze([
            PhelConfig::SRC_DIRS => ['/absolute/src'],
        ]);

        self::assertCount(1, $issues);
        self::assertSame(ConfigIssueLevel::Error, $issues[0]->level);
        self::assertStringContainsString('/absolute/src', $issues[0]->message);
        self::assertStringContainsString('should be relative', $issues[0]->message);
    }

    public function test_absolute_dirs_across_keys_are_aggregated(): void
    {
        $issues = $this->diagnostics->analyze([
            PhelConfig::SRC_DIRS => ['/abs/src'],
            PhelConfig::TEST_DIRS => ['/abs/tests'],
            PhelConfig::VENDOR_DIR => '/abs/vendor',
        ]);

        self::assertCount(3, $issues);
        foreach ($issues as $issue) {
            self::assertSame(ConfigIssueLevel::Error, $issue->level);
        }
    }
}
