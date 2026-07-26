<?php

declare(strict_types=1);

namespace PhelTest\Integration\Api;

use Phel;
use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;
use Phel\Lang\Symbol;
use PhelTest\Support\CoreApiSurface;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

use function file_get_contents;

/**
 * The source-compatibility gate for the Phel standard library.
 *
 * Promise 1 in `docs/stability.md` is that Phel source compiling on 1.0.0 still
 * compiles on every later 1.x. A definition that disappears breaks that; so does
 * one that quietly loses an arity or gains a required parameter, and that second
 * kind is the one nobody notices until a user reports it.
 *
 * {@see PublicApiSurfaceTest} is the same gate for the PHP embedding API.
 */
final class CoreApiSurfaceTest extends TestCase
{
    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_the_standard_library_matches_the_committed_snapshot(): void
    {
        Phel::bootstrap(__DIR__);
        Phel::clear();
        Symbol::resetGen();
        GlobalEnvironmentSingleton::initializeNew();

        $snapshotPath = CoreApiSurface::snapshotPath();
        self::assertFileExists($snapshotPath, 'Run `composer core-api:update` to create it.');

        self::assertSame(
            (string) file_get_contents($snapshotPath),
            CoreApiSurface::withApiFacade()->render(),
            "The public Phel API changed.\n"
            . "A removed definition or a removed arity breaks source compatibility; inside 1.x\n"
            . "neither is allowed without a major. Add the CHANGELOG entry, then run\n"
            . '`composer core-api:update`.',
        );
    }
}
