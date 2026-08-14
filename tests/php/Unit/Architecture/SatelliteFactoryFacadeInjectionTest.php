<?php

declare(strict_types=1);

namespace PhelTest\Unit\Architecture;

use Generator;
use Phel\Lint\LintFacade;
use Phel\Run\RunFacade;
use Phel\Shared\Facade\RunFacadeInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Satellite modules must depend on the Shared facade *contracts*, not on a
 * neighbour module's concrete facade. The factory getter return type is the
 * one place that pins this down, so we lock it here against regressions.
 */
final class SatelliteFactoryFacadeInjectionTest extends TestCase
{
    use ScansPhpSourcesTrait;

    /**
     * Walks every factory and fails on the difference. This replaced an
     * 18-row hand-maintained provider listing each getter and its expected
     * interface: the provider could only ever see getters somebody remembered
     * to add, so it cost maintenance per new module and could not notice a
     * *new* factory binding a concrete facade, which is the failure that
     * matters (#3062).
     *
     * Exactly one getter still returns a concrete facade: `Lsp` consumes
     * `LintFacade`, for which `src/php/CLAUDE.md` records that no interface
     * exists. Unlike the Api and Formatter contracts, widening that one is not
     * a signature change: `LintFacade` trades in `RuleSettings`, `LintCache`
     * and `LintResult`, so a Shared contract means relocating Lint's own
     * configuration and cache types first. It is listed rather than silently
     * allowed.
     */
    public function test_no_unlisted_factory_binds_a_concrete_facade(): void
    {
        $expected = [
            'Lsp/LspFactory.php::getLintFacade' => LintFacade::class,
        ];

        self::assertSame(
            $expected,
            $this->concreteFacadeGetters(),
            "A factory getter returns a concrete facade instead of a Shared *FacadeInterface.\n"
            . "src/php/CLAUDE.md requires injecting the interface. Add the method to the contract\n"
            . 'in Shared/Facade and widen the return type; only list it here if that is not yet possible.',
        );
    }

    /**
     * The return type is searched rather than matched whole, so these shapes
     * are seen where the previous `:\s*(\w*Facade)\b` saw only the first.
     * A `*FacadeInterface` must stay invisible: depending on the contract is
     * the thing this test is asking for.
     */
    #[DataProvider('returnTypeShapeProvider')]
    public function test_a_concrete_facade_is_found_in_every_return_type_shape(
        string $returnType,
        array $expected,
    ): void {
        $method = new ReflectionMethod(self::class, 'facadeShortNamesIn');

        self::assertSame($expected, $method->invoke($this, $returnType));
    }

    /**
     * @return Generator<string, array{string, list<string>}>
     */
    public static function returnTypeShapeProvider(): Generator
    {
        yield 'plain' => ['RunFacade', ['RunFacade']];
        yield 'nullable' => ['?RunFacade', ['RunFacade']];
        yield 'union with null' => ['RunFacade|null', ['RunFacade']];
        yield 'rooted' => [RunFacade::class, ['RunFacade']];
        yield 'union of two facades' => ['RunFacade|LintFacade', ['RunFacade', 'LintFacade']];
        yield 'interface is not a concrete facade' => ['RunFacadeInterface', []];
        yield 'nullable interface' => ['?RunFacadeInterface', []];
        yield 'unrelated' => ['string', []];
    }

    public function test_run_facade_interface_declares_auto_detect_entry_point(): void
    {
        self::assertTrue(
            method_exists(RunFacadeInterface::class, 'autoDetectEntryPoint'),
            'Profile consumes RunFacade::autoDetectEntryPoint via the Shared contract.',
        );
    }

    /**
     * @return array<string, string> `<relative factory>::<getter>` => concrete facade FQN
     */
    private function concreteFacadeGetters(): array
    {
        $found = [];

        foreach ($this->phpFilesIn('src/php') as $relative => $contents) {
            if (!str_ends_with($relative, 'Factory.php')) {
                continue;
            }

            // The return type is captured whole and then searched, rather than
            // matched as a bare `\w*Facade`. Gacela resolves a pillar by
            // filename suffix at any depth, and a getter may declare `?XFacade`
            // or `XFacade|null`; the narrower pattern saw none of those.
            preg_match_all(
                '/function\s+(\w+)\s*\(\s*\)\s*:\s*([^\s{;]+)/',
                $contents,
                $matches,
                PREG_SET_ORDER,
            );

            foreach ($matches as [, $method, $returnType]) {
                foreach ($this->facadeShortNamesIn($returnType) as $shortName) {
                    if (preg_match('/^use\s+(Phel\\\\[\w\\\\]*\\\\' . $shortName . ');$/m', $contents, $import) !== 1) {
                        continue;
                    }

                    $found[$relative . '::' . $method] = $import[1];
                }
            }
        }

        ksort($found);

        return $found;
    }

    /**
     * Concrete `*Facade` names inside a return type, which may be nullable
     * (`?XFacade`), a union (`XFacade|null`), or rooted (`\\Phel\\X\\XFacade`).
     * A `*FacadeInterface` is deliberately not one: that is the contract this
     * test wants factories to depend on.
     *
     * @return list<string>
     */
    private function facadeShortNamesIn(string $returnType): array
    {
        preg_match_all('/(\w*Facade)(?![\w])/', $returnType, $matches);

        return $matches[1];
    }
}
