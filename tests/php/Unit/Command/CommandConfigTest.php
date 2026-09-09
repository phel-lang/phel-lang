<?php

declare(strict_types=1);

namespace PhelTest\Unit\Command;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use Phel\Command\CommandConfig;
use Phel\Config\PhelConfig;
use PHPUnit\Framework\TestCase;

final class CommandConfigTest extends TestCase
{
    public function test_collapsed_trace_hint_names_the_flag_and_the_log_relative_to_the_project(): void
    {
        $this->bootstrapWithErrorLogFile('.phel/error.log');

        self::assertSame(
            '--stack-trace to show, full trace in .phel/error.log',
            new CommandConfig()->getCollapsedTraceHint(),
        );
    }

    public function test_collapsed_trace_hint_keeps_a_log_path_outside_the_project_absolute(): void
    {
        $this->bootstrapWithErrorLogFile('/var/log/phel/error.log');

        self::assertSame(
            '--stack-trace to show, full trace in /var/log/phel/error.log',
            new CommandConfig()->getCollapsedTraceHint(),
        );
    }

    private function bootstrapWithErrorLogFile(string $errorLogFile): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config) use ($errorLogFile): void {
            $config->addAppConfigKeyValue(PhelConfig::ERROR_LOG_FILE, $errorLogFile);
        });
    }
}
