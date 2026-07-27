<?php

declare(strict_types=1);

namespace PhelTest\Integration\Run\RequireBuildMode;

use Phel;
use Phel\Build\BuildFacade;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

use function file_exists;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

final class RequireBuildModeTest extends TestCase
{
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_require_loads_in_build_mode(): void
    {
        Phel::bootstrap(__DIR__);

        // Load phel.core so the required fixture namespace compiles.
        new BuildFacade()->compileFile(
            __DIR__ . '/../../../../../src/phel/core.phel',
            tempnam(sys_get_temp_dir(), 'phel-core'),
        );

        $fixtures = __DIR__ . '/Fixtures';
        $executedFile = $fixtures . '/example/executed.txt';
        if (file_exists($executedFile)) {
            unlink($executedFile);
        }

        new BuildFacade()->evalFile($fixtures . '/example/main.phel');

        self::assertTrue(
            Phel::isNamespaceLoaded('buildmode.game'),
            'The (:require ...) of main.phel must actually load buildmode.game.',
        );
        self::assertFileDoesNotExist(
            $executedFile,
            'A namespace pulled in via :require evaluates its top level in build mode.',
        );
    }
}
