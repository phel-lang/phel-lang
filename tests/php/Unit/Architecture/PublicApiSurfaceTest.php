<?php

declare(strict_types=1);

namespace PhelTest\Unit\Architecture;

use PhelTest\Support\PublicApiSurface;
use PHPUnit\Framework\TestCase;

use function dirname;
use function file_get_contents;
use function sprintf;

/**
 * The backward-compatibility gate for the PHP embedding API.
 *
 * `docs/stability.md` says which symbols semver covers; this test is the half a
 * machine can check. It renders the whole public surface and compares it against
 * a committed snapshot, so a signature change to a public class fails the pull
 * request that makes it rather than surfacing in a release note afterwards.
 *
 * A failure is not automatically a bug. It means: decide whether the change is
 * breaking, write the changelog entry, then run `composer api-surface:update`.
 * That diff is the review.
 *
 * Comparing against a committed file rather than the last release tag is
 * deliberate. A break is cheapest to discuss while the diff causing it is still
 * open, and a tag comparison cannot run until the tag exists.
 */
final class PublicApiSurfaceTest extends TestCase
{
    public function test_the_public_api_matches_the_committed_snapshot(): void
    {
        $snapshotPath = PublicApiSurface::snapshotPath();
        self::assertFileExists($snapshotPath, 'Run `composer api-surface:update` to create it.');

        $expected = (string) file_get_contents($snapshotPath);
        $actual = PublicApiSurface::fromRepositoryRoot(PublicApiSurface::repositoryRoot())->render();

        self::assertSame(
            $expected,
            $actual,
            "The public PHP API changed.\n"
            . "Decide whether the change is breaking (docs/stability.md lists which shapes are),\n"
            . 'add the CHANGELOG entry, then run `composer api-surface:update`.',
        );
    }

    /**
     * The rules are only worth enforcing if they still select the surface the
     * policy describes. A module renamed or a facade moved out from under its
     * module root would otherwise silently drop out of the snapshot, and a
     * dropped symbol is exactly what the gate exists to catch.
     */
    public function test_every_module_facade_is_part_of_the_surface(): void
    {
        $surface = PublicApiSurface::fromRepositoryRoot(PublicApiSurface::repositoryRoot());

        foreach (glob(PublicApiSurface::repositoryRoot() . '/src/php/*/*Facade.php') ?: [] as $path) {
            $module = basename(dirname($path));
            $className = sprintf('Phel\\%s\\%s', $module, basename($path, '.php'));

            self::assertTrue(
                $surface->isPublicSymbol($className),
                sprintf('%s is a module facade but the surface rules do not select it.', $className),
            );
        }
    }

    public function test_internal_layers_are_excluded_from_the_surface(): void
    {
        $surface = PublicApiSurface::fromRepositoryRoot(PublicApiSurface::repositoryRoot());

        $internal = [
            'Phel\\Compiler\\Domain\\Analyzer\\Analyzer',
            'Phel\\Compiler\\Application\\Lexer',
            'Phel\\Run\\Infrastructure\\Command\\ReplCommand',
            'Phel\\Compiler\\CompilerFactory',
            'Phel\\Run\\RunDependencyProvider',
        ];

        foreach ($internal as $className) {
            self::assertFalse(
                $surface->isPublicSymbol($className),
                sprintf('%s is internal but the surface rules select it as public.', $className),
            );
        }
    }
}
