<?php

declare(strict_types=1);

namespace PhelTest\Integration\Compiler;

use Phel\Build\BuildFacade;
use Phel\Lang\GetIn;

use function implode;
use function range;
use function sprintf;
use function strlen;

/**
 * A literal-path `get-in` compiles to one `GetIn::path` call (#3320). Each
 * argument is written once, so nesting the calls must grow the emitted code
 * linearly. A shape that repeats its arguments in both arms of a ternary
 * doubles per level instead: 14 nested `(get :a {})` once emitted 6.4MB.
 */
final class GetInLiteralPathLoweringTest extends AbstractCompilerRuntimeTestCase
{
    public function test_nested_lookups_grow_the_emitted_code_linearly(): void
    {
        $eight = strlen($this->compileNested(8));
        $sixteen = strlen($this->compileNested(16));

        self::assertLessThan(3 * $eight, $sixteen);
    }

    public function test_a_long_path_grows_the_emitted_code_linearly(): void
    {
        $eight = strlen($this->compileLongPath(8));
        $sixteen = strlen($this->compileLongPath(16));

        self::assertLessThan(3 * $eight, $sixteen);
    }

    public function test_the_lowered_call_never_reaches_the_core_fn(): void
    {
        $php = $this->compileNested(4);

        self::assertStringNotContainsString('"get-in"', $php);
        self::assertStringContainsString(GetIn::class . '::path(', $php);
    }

    private function compileNested(int $depth): string
    {
        $steps = '';
        foreach (range(1, $depth) as $i) {
            $steps .= sprintf(' (get-in [:k%d] {})', $i);
        }

        return $this->compileInBuildMode(sprintf('(fn [m] (-> m%s))', $steps));
    }

    private function compileLongPath(int $length): string
    {
        $keys = implode(' ', array_map(static fn(int $i): string => ':k' . $i, range(1, $length)));

        return $this->compileInBuildMode(sprintf('(fn [m] (get-in m [%s] :nf))', $keys));
    }

    private function compileInBuildMode(string $phel): string
    {
        BuildFacade::enableBuildMode();
        try {
            return $this->compilerFacade->compile($phel)->getPhpCode();
        } finally {
            BuildFacade::disableBuildMode();
        }
    }
}
