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

        Phel::addDefinition('phel\\repl', 'src-dirs', [$fixtures]);

        $executedFile = $fixtures . '/example/executed.txt';
        if (file_exists($executedFile)) {
            unlink($executedFile);
        }

        (new BuildFacade())->evalFile($fixtures . '/example/main.phel');

        self::assertFileDoesNotExist($executedFile);
    }
}
