<?php

declare(strict_types=1);

namespace PhelTest\Integration\Run\LoadInNs;

use Phel;
use Phel\Build\BuildFacade;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class LoadInNsTest extends TestCase
{
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_nested_load_with_in_ns_resolves_relative_paths_correctly(): void
    {
        $fixtures = __DIR__ . '/Fixtures';
        Phel::bootstrap(__DIR__);

        Phel::addDefinition('phel\\repl', 'src-dirs', [$fixtures]);

        (new BuildFacade())->evalFile($fixtures . '/example/main.phel');

        // All three files should execute successfully and set their definitions
        self::assertTrue(Phel::getDefinition('loadinns\\main', 'main-loaded'));
        self::assertTrue(Phel::getDefinition('loadinns\\main', 'util-loaded'));
        self::assertTrue(Phel::getDefinition('loadinns\\main', 'helper-loaded'));
    }
}
