<?php

declare(strict_types=1);

namespace PhelTest\Unit\Architecture;

use PHPUnit\Framework\TestCase;

use function sprintf;

/**
 * Locks the layering *inside* each module: `Infrastructure -> Application ->
 * Domain`, with `Domain` depending on nothing further out.
 *
 * {@see ModuleDependencyCycleTest} only sees module-to-module edges, so it is
 * blind to this entirely: a `Domain` class importing from `Infrastructure` is
 * invisible there because both sides report the same module. That makes this the
 * cheapest place for erosion to accumulate unnoticed.
 *
 * Imports used only as a `::class` literal are ignored, matching the policy
 * {@see ModuleDependencyCycleTest} already documents: the emitter bakes class
 * names into generated PHP as strings, which reads like a dependency to a
 * scanner but binds nothing at runtime and cannot be inverted, since the FQN is
 * part of the compiled-artifact ABI.
 */
final class IntraModuleLayeringTest extends TestCase
{
    use ScansPhpSourcesTrait;

    /**
     * Outward distance from the centre. An import is a violation when its layer
     * ranks strictly higher than the importing file's layer.
     *
     * @var array<string, int>
     */
    private const array LAYER_RANK = [
        'Domain' => 0,
        'Application' => 1,
        'Infrastructure' => 2,
    ];

    /**
     * The only outward imports that bind something at runtime today.
     *
     * `DebugLineTap` is a process-global tracer toggled by `phel run --debug`.
     * The evaluators consult it inline while building the PHP they are about to
     * `eval`, so inverting it means threading a trace-state port through the
     * evaluator constructors — behaviour-bearing work on the eval path for a
     * read-only boolean. Documented here instead, deliberately.
     *
     * @var list<string>
     */
    private const array ACCEPTED_OUTWARD_IMPORTS = [
        'Compiler/Application/DebugLineTapController.php -> Phel\Compiler\Infrastructure\Service\DebugLineTap',
        'Compiler/Domain/Evaluator/InMemoryEvaluator.php -> Phel\Compiler\Infrastructure\Service\DebugLineTap',
        'Compiler/Domain/Evaluator/RequireEvaluator.php -> Phel\Compiler\Infrastructure\Service\DebugLineTap',
    ];

    public function test_no_module_layer_depends_on_a_layer_further_out(): void
    {
        self::assertSame(
            self::ACCEPTED_OUTWARD_IMPORTS,
            $this->outwardImports(),
            "A module layer now imports one further out (Domain -> Application/Infrastructure,\n"
            . "or Application -> Infrastructure), which inverts the Gacela direction.\n"
            . "Fix it by moving the class inward (a Null Object or value object belongs in Domain)\n"
            . "or by declaring an interface in the inner layer for the outer one to implement.\n"
            . 'Removing an entry is good news: drop it from ACCEPTED_OUTWARD_IMPORTS.',
        );
    }

    /**
     * @return list<string> `<relative file> -> <imported FQN>`, sorted
     */
    private function outwardImports(): array
    {
        $outward = [];

        foreach ($this->phpFilesIn('src/php') as $relative => $contents) {
            [$module, $layer] = $this->moduleAndLayerOf($contents);
            if ($module === null) {
                continue;
            }

            if (!isset(self::LAYER_RANK[(string) $layer])) {
                continue;
            }

            foreach ($this->importsUnder($contents, 'Phel') as [$_, $fqn]) {
                $segments = explode('\\', $fqn);

                if (($segments[1] ?? null) !== $module) {
                    continue;
                }

                $targetLayer = $segments[2] ?? '';
                if (!isset(self::LAYER_RANK[$targetLayer])) {
                    continue;
                }

                if (self::LAYER_RANK[$targetLayer] <= self::LAYER_RANK[(string) $layer]) {
                    continue;
                }

                if ($this->isClassLiteralOnly($contents, $fqn)) {
                    continue;
                }

                $outward[] = sprintf('%s -> %s', $relative, $fqn);
            }
        }

        sort($outward);

        return $outward;
    }

    /**
     * True when the short name only ever appears as `Short::class`, i.e. the
     * file needs the name as a string and never touches the class itself.
     */
    private function isClassLiteralOnly(string $contents, string $fqn): bool
    {
        $segments = explode('\\', $fqn);
        $shortName = end($segments);
        $boundary = '/(?<![\w\\\\])' . preg_quote($shortName, '/') . '\b/';

        $withoutImport = (string) preg_replace(
            '/^use\s+(?:function\s+|const\s+)?' . preg_quote($fqn, '/') . ';$\n?/m',
            '',
            $contents,
        );

        $withoutLiterals = (string) preg_replace(
            '/(?<![\w\\\\])' . preg_quote($shortName, '/') . '::class\b/',
            '',
            $withoutImport,
        );

        return preg_match($boundary, $withoutLiterals) !== 1;
    }

    /**
     * @return array{?string, ?string} [module, layer]
     */
    private function moduleAndLayerOf(string $contents): array
    {
        if (preg_match('/^namespace\s+(Phel(?:\\\\\w+)*);/m', $contents, $matches) !== 1) {
            return [null, null];
        }

        $segments = explode('\\', $matches[1]);

        return [$segments[1] ?? null, $segments[2] ?? null];
    }
}
