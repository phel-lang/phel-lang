<?php

declare(strict_types=1);

namespace PhelTest\Unit\Architecture;

use PHPUnit\Framework\TestCase;

use function sprintf;

/**
 * Locks the module-level dependency graph of `src/php` against new cycles.
 *
 * The graph is built from `use Phel\<Module>\…;` statements, which is the only
 * form that creates real compile-time coupling. Docblock `{@see}` tags and
 * class names embedded in generated-code templates are deliberately ignored:
 * they read like edges to a naive scanner but bind nothing.
 *
 * Four cyclic module pairs exist today and each is documented where it lives.
 * Three of them are one file wide on at least one side, so a regression shows
 * up as a *new* file in {@see self::CYCLE_CLOSING_FILES} rather than as a new
 * pair, which is why both are asserted.
 *
 * @see SharedCompilerBoundaryTest for the Shared -> Compiler back-edge
 */
final class ModuleDependencyCycleTest extends TestCase
{
    use ScansPhpSourcesTrait;

    /**
     * Every module pair that points at each other, smaller name first.
     *
     * - `Compiler <-> Shared`: accepted in #2785; rationale in `src/php/Shared/CLAUDE.md`.
     * - `Lang <-> Shared`: accepted; `src/php/Lang/CLAUDE.md` names both edges
     *   (`AbstractType::__toString` needs `Printer`, `AbstractPersistentStruct` needs `Munge`).
     * - `Api <-> Run`: the only mutual Gacela provider pair (see below).
     * - `Phel <-> Run`: the composition root wires `RunFacade`, and `RunCommand`
     *   calls back into `Phel::setupRuntimeArgs()`.
     *
     * @var list<string>
     */
    private const array KNOWN_CYCLES = [
        'Api <-> Run',
        'Compiler <-> Shared',
        'Lang <-> Shared',
        'Phel <-> Run',
    ];

    /**
     * The files that close each direction of the three narrow cycles, relative
     * to `src/php`. `Compiler <-> Shared` is excluded: its Compiler -> Shared
     * side is ~80 files by design, and its single back-edge file is already
     * pinned by {@see SharedCompilerBoundaryTest}.
     *
     * @var array<string, list<string>>
     */
    private const array CYCLE_CLOSING_FILES = [
        'Api -> Run' => ['Api/ApiProvider.php'],
        'Run -> Api' => ['Run/RunProvider.php'],
        'Lang -> Shared' => ['Lang/Collections/Struct/AbstractPersistentStruct.php', 'Lang/TypeStringifier.php'],
        'Phel -> Run' => ['Phel.php'],
        'Run -> Phel' => ['Run/Infrastructure/Command/RunCommand.php'],
    ];

    /**
     * Gacela providers naming each other's concrete facade is the strongest
     * form of module cycle: it couples the wiring, not just a type signature.
     * Exactly one such pair is tolerated.
     *
     * @var list<string>
     */
    private const array KNOWN_PROVIDER_CYCLES = [
        'Api <-> Run',
    ];

    /** @var array<string, array<string, list<string>>>|null from module => to module => relative files */
    private static ?array $edges = null;

    public function test_module_dependency_cycles_do_not_grow(): void
    {
        self::assertSame(
            self::KNOWN_CYCLES,
            $this->cyclicPairs($this->moduleEdges()),
            "The set of cyclic module pairs in src/php changed.\n"
            . "Removing one is good news: drop it from KNOWN_CYCLES.\n"
            . 'Adding one needs a documented rationale in the owning module CLAUDE.md first.',
        );
    }

    public function test_narrow_cycles_are_not_widened(): void
    {
        $edges = $this->moduleEdges();

        foreach (self::CYCLE_CLOSING_FILES as $edge => $expected) {
            [$from, $to] = explode(' -> ', $edge);

            self::assertSame(
                $expected,
                $edges[$from][$to] ?? [],
                sprintf(
                    "The %s edge is no longer confined to its documented file(s).\n"
                    . 'A cycle that widens from one file to many stops being a wiring detail and becomes structural.',
                    $edge,
                ),
            );
        }
    }

    public function test_no_new_mutual_gacela_provider_dependencies(): void
    {
        self::assertSame(
            self::KNOWN_PROVIDER_CYCLES,
            $this->cyclicPairs($this->providerEdges()),
            "Two Gacela providers now require each other's concrete facade.\n"
            . 'Break it by moving the shared behaviour into Phel\\Shared, or by having one side '
            . 'depend on the module its neighbour was merely delegating to.',
        );
    }

    /**
     * @param array<string, array<string, list<string>>> $edges
     *
     * @return list<string>
     */
    private function cyclicPairs(array $edges): array
    {
        $pairs = [];

        foreach ($edges as $from => $targets) {
            foreach (array_keys($targets) as $to) {
                if (!isset($edges[$to][$from])) {
                    continue;
                }

                $pairs[] = $from < $to
                    ? sprintf('%s <-> %s', $from, $to)
                    : sprintf('%s <-> %s', $to, $from);
            }
        }

        $pairs = array_values(array_unique($pairs));
        sort($pairs);

        return $pairs;
    }

    /**
     * @return array<string, array<string, list<string>>>
     */
    private function moduleEdges(): array
    {
        if (self::$edges !== null) {
            return self::$edges;
        }

        $edges = [];

        foreach ($this->sourceFiles() as $relative => $contents) {
            $from = $this->moduleOfNamespace($contents);
            if ($from === null) {
                continue;
            }

            preg_match_all('/^use\s+(?:function\s+|const\s+)?Phel\\\\(\w+)/m', $contents, $matches);

            foreach (array_unique($matches[1]) as $to) {
                if ($to !== $from) {
                    $edges[$from][$to][] = $relative;
                }
            }
        }

        foreach ($edges as &$targets) {
            foreach ($targets as &$files) {
                sort($files);
            }
        }

        return self::$edges = $edges;
    }

    /**
     * The same graph restricted to Gacela `*Provider` classes.
     *
     * @return array<string, array<string, list<string>>>
     */
    private function providerEdges(): array
    {
        $providerEdges = [];

        foreach ($this->moduleEdges() as $from => $targets) {
            foreach ($targets as $to => $files) {
                $providers = array_values(array_filter(
                    $files,
                    static fn(string $file): bool => str_ends_with($file, 'Provider.php'),
                ));

                if ($providers !== []) {
                    $providerEdges[$from][$to] = $providers;
                }
            }
        }

        return $providerEdges;
    }

    private function moduleOfNamespace(string $contents): ?string
    {
        if (!preg_match('/^namespace\s+(Phel(?:\\\\\w+)*);/m', $contents, $matches)) {
            return null;
        }

        // Root-level files (`src/php/Phel.php`) form the composition root, which
        // the graph treats as a module of its own.
        return explode('\\', $matches[1])[1] ?? 'Phel';
    }

    /**
     * @return array<string, string> relative path => file contents
     */
    private function sourceFiles(): array
    {
        return $this->phpFilesIn('src/php');
    }
}
