<?php

declare(strict_types=1);

namespace PhelTest\Unit\Architecture;

use PHPUnit\Framework\TestCase;

use function count;
use function in_array;
use function sprintf;

/**
 * A Factory may only `new` classes from its own module or from `Phel\Shared`.
 *
 * This is the rule that keeps the module graph honest at the wiring level: a
 * Factory reaching into `Phel\<Other>\Application\…`, `…\Domain\…`, or
 * `…\Infrastructure\…` couples two modules' internals, and no Facade boundary
 * can be re-established afterwards without touching both sides.
 *
 * There are two sanctioned ways out when a Factory wants a neighbour's class:
 * move a pure stateless utility into `Phel\Shared`, or inject the owning
 * module's `*FacadeInterface` via the `DependencyProvider`. Adding a
 * `createX()` passthrough to a neighbour Facade so this side can keep calling
 * `new` is explicitly the wrong fix — it launders the coupling instead of
 * removing it.
 *
 * `Phel\<Other>\Domain\…Interface` imported purely as a type hint is allowed by
 * `src/php/CLAUDE.md`, but nothing does it today, so the assertion stays at its
 * strictest: zero cross-module layer imports at all. Should a genuine type-hint
 * case appear, relax it to interfaces only rather than adding an exception list.
 *
 * @see SatelliteFactoryFacadeInjectionTest which pins the return types of the
 *      facade getters this rule pushes factories towards
 */
final class FactoryModuleBoundaryTest extends TestCase
{
    use ScansPhpSourcesTrait;

    /**
     * Nested namespace segments that mark a module's internals. A Factory may
     * reach into these only within its own module.
     *
     * @var list<string>
     */
    private const array MODULE_LAYERS = ['Application', 'Domain', 'Infrastructure'];

    /**
     * `Shared` is a dependency-free leaf of pure utilities and contracts, so
     * every Factory may consume it directly.
     */
    private const string SHARED_MODULE = 'Shared';

    public function test_no_factory_reaches_into_another_modules_internals(): void
    {
        $violations = [];

        foreach ($this->factories() as $relative => $contents) {
            $ownModule = $this->moduleOf($contents);

            foreach ($this->importsUnder($contents, 'Phel') as [$line, $fqn]) {
                if (!$this->crossesModuleBoundary($fqn, $ownModule)) {
                    continue;
                }

                $violations[] = sprintf('src/php/%s:%d imports %s', $relative, $line, $fqn);
            }

            foreach ($this->fullyQualifiedInstantiations($contents) as $fqn) {
                if (!$this->crossesModuleBoundary($fqn, $ownModule)) {
                    continue;
                }

                $violations[] = sprintf('src/php/%s instantiates \\%s', $relative, $fqn);
            }
        }

        self::assertSame(
            [],
            $violations,
            "A Factory may only new classes from its own module or Phel\\Shared.\n"
            . "Move a pure stateless utility into Phel\\Shared, or inject the owning module's\n"
            . "*FacadeInterface via its DependencyProvider. Do NOT add a createX() passthrough\n"
            . 'to the neighbour Facade just so this Factory can keep calling new.',
        );
    }

    /**
     * Guards the scan itself: a typo in the glob would make the assertion above
     * pass vacuously.
     */
    public function test_every_gacela_module_factory_is_scanned(): void
    {
        $factories = $this->factories();

        foreach (['Compiler/CompilerFactory.php', 'Run/RunFactory.php', 'Build/BuildFactory.php'] as $expected) {
            self::assertArrayHasKey($expected, $factories);
        }

        self::assertGreaterThanOrEqual(
            15,
            count($factories),
            'Far fewer factories than expected — the scan is probably no longer finding them.',
        );
    }

    /**
     * @return array<string, string> relative path => file contents
     */
    private function factories(): array
    {
        return array_filter(
            $this->phpFilesIn('src/php'),
            static fn(string $_, string $relative): bool => str_ends_with($relative, 'Factory.php'),
            ARRAY_FILTER_USE_BOTH,
        );
    }

    private function crossesModuleBoundary(string $fqn, ?string $ownModule): bool
    {
        $segments = explode('\\', $fqn);
        $module = $segments[1] ?? null;
        $layer = $segments[2] ?? null;

        if (in_array($module, [null, $ownModule, self::SHARED_MODULE], true)) {
            return false;
        }

        return in_array($layer, self::MODULE_LAYERS, true);
    }

    /**
     * `new \Phel\Other\Domain\Thing()` sidesteps the import list entirely.
     *
     * @return list<string>
     */
    private function fullyQualifiedInstantiations(string $contents): array
    {
        preg_match_all('/new\s+\\\\(Phel\\\\[\w\\\\]+)/', $contents, $matches);

        return array_values(array_unique($matches[1]));
    }

    private function moduleOf(string $contents): ?string
    {
        if (preg_match('/^namespace\s+Phel\\\\(\w+)/m', $contents, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }
}
