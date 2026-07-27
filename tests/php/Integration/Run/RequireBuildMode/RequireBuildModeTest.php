<?php

declare(strict_types=1);

namespace PhelTest\Integration\Run\RequireBuildMode;

use Phel;
use Phel\Build\BuildFacade;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class RequireBuildModeTest extends TestCase
{
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_require_loads_in_build_mode(): void
    {
        $fixtures = __DIR__ . '/Fixtures';
        Phel::bootstrap(__DIR__);

        // `game.phel` calls `str` and reads `*build-mode*` at its top level, so
        // the required file cannot compile without core in the registry. Until
        // #2886 the require resolved nothing here, so the file was never loaded
        // and this passed without ever exercising the path it names.
        new BuildFacade()->compileFile(
            __DIR__ . '/../../../../../src/phel/core.phel',
            tempnam(sys_get_temp_dir(), 'phel-core'),
        );

        Phel::addDefinition('phel\\repl', 'src-dirs', [$fixtures]);

        $executedFile = $fixtures . '/example/executed.txt';
        if (file_exists($executedFile)) {
            unlink($executedFile);
        }

        new BuildFacade()->evalFile($fixtures . '/example/main.phel');

        // Assert the require actually happened first. On its own, the
        // file-absence assertion below is satisfied by never loading the
        // dependency at all, which is how this test stayed green while loading
        // nothing (#2886).
        self::assertTrue(
            Phel::isNamespaceLoaded('buildmode.game'),
            'The required namespace must be loaded.',
        );
        self::assertFileDoesNotExist(
            $executedFile,
            'The dependency loads under build mode, so its non-build side effect must not run.',
        );
    }
}
