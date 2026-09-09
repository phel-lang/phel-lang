<?php

declare(strict_types=1);

namespace PhelTest\Unit\Mutate\Application;

use Phel\Mutate\Application\PcovScopeWidener;
use PHPUnit\Framework\TestCase;

use function extension_loaded;

use const DIRECTORY_SEPARATOR;

final class PcovScopeWidenerTest extends TestCase
{
    public function test_without_pcov_a_worker_gets_no_extra_ini(): void
    {
        self::assertSame([], new PcovScopeWidener(false)->iniArguments());
    }

    public function test_with_pcov_the_worker_collects_from_the_filesystem_root(): void
    {
        // Anything narrower misses the temp directory Phel executes its
        // compiled PHP from, which is the whole point of the widening.
        self::assertSame(
            ['-d', 'pcov.directory=' . DIRECTORY_SEPARATOR],
            new PcovScopeWidener(true)->iniArguments(),
        );
    }

    public function test_detect_reads_the_extensions_of_the_running_process(): void
    {
        self::assertSame(
            new PcovScopeWidener(extension_loaded('pcov'))->iniArguments(),
            PcovScopeWidener::detect()->iniArguments(),
        );
    }
}
