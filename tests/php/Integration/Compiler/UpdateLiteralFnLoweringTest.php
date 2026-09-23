<?php

declare(strict_types=1);

namespace PhelTest\Integration\Compiler;

use Phel\Build\BuildFacade;
use PHPUnit\Framework\Attributes\DataProvider;

use function range;
use function sprintf;
use function str_repeat;
use function strlen;

/**
 * An `update` whose function is a literal one-arity `fn` compiles to a read,
 * the body and one `assoc` (#3322). Every argument and the body are written
 * once, so nesting must grow the emitted code linearly, both threaded through
 * the target and nested inside the body. A shape that repeats its arguments
 * in both arms of a ternary doubles per level instead.
 */
final class UpdateLiteralFnLoweringTest extends AbstractCompilerRuntimeTestCase
{
    public function test_threaded_updates_grow_the_emitted_code_linearly(): void
    {
        $eight = strlen($this->compileThreaded(8));
        $sixteen = strlen($this->compileThreaded(16));

        self::assertLessThan(3 * $eight, $sixteen);
    }

    public function test_updates_nested_in_the_body_grow_the_emitted_code_linearly(): void
    {
        $eight = strlen($this->compileNestedInBody(8));
        $sixteen = strlen($this->compileNestedInBody(16));

        self::assertLessThan(3 * $eight, $sixteen);
    }

    #[DataProvider('providerLoweredShapes')]
    public function test_the_lowered_call_builds_no_closure_and_never_reaches_update(string $phel): void
    {
        $php = $this->compileInBuildMode($phel);

        self::assertStringNotContainsString('"update"', $php);
        self::assertStringNotContainsString('function($v', $php);
        self::assertStringContainsString('"assoc"', $php);
    }

    public static function providerLoweredShapes(): iterable
    {
        yield 'return position' => ['(fn [m] (update m :k (fn [v] (php/+ v 1))))'];
        yield 'expression position' => ['(fn [m] [(update m :k (fn [v] (php/+ v 1)))])'];
        yield 'extra arguments' => ['(fn [m a b] (update m :k (fn [v x y] [v x y]) a b))'];
        yield 'past the fixed arities' => ['(fn [m] (update m :k (fn [v a b c d] [v a b c d]) 1 2 3 4))'];
        yield 'a destructured param' => ['(fn [m] (update m :k (fn [[v w]] [w v])))'];
        yield 'several body forms' => ['(fn [m] (update m :k (fn [v] (println v) v)))'];
        yield 'a nested fn capturing the param' => ['(fn [m] (update m :k (fn [v] (fn [] v))))'];
    }

    #[DataProvider('providerRuntimeShapes')]
    public function test_other_shapes_keep_the_runtime_call(string $phel): void
    {
        self::assertStringContainsString('"update"', $this->compileInBuildMode($phel));
    }

    public static function providerRuntimeShapes(): iterable
    {
        yield 'a fn value' => ['(fn [m f] (update m :k f))'];
        yield 'a rest param' => ['(fn [m] (update m :k (fn [v & xs] v)))'];
        yield 'several arities' => ['(fn [m] (update m :k (fn ([v] v) ([v x] x))))'];
        yield 'recur' => ['(fn [m] (update m :k (fn [v] (if v (recur nil) 1))))'];
        yield 'a named fn' => ['(fn [m] (update m :k (fn self [v] v)))'];
        yield 'a condition map' => ['(fn [m] (update m :k (fn [v] {:pre [v]} v)))'];
        yield 'a tagged param' => ['(fn [m] (update m :k (fn [^int v] v)))'];
        yield 'a tagged return' => ['(fn [m] (update m :k (fn ^int [v] v)))'];
        yield 'a param count the call does not fill' => ['(fn [m] (update m :k (fn [v x] v)))'];
        yield 'apply' => ['(fn [m] (apply update m :k [(fn [v] v)]))'];
        yield 'statement position' => ['(fn [m] (update m :k (fn [v] v)) nil)'];
        yield 'php/aset on an outer local' => ['(fn [m arr] (update m :k (fn [v] (php/aset arr 0 v) v)))'];
        yield 'an outer local passed to php' => ['(fn [m arr] (update m :k (fn [v] (php/sort arr) v)))'];
        yield 'an outer local passed to a method' => ['(fn [m o arr] (update m :k (fn [v] (.fill o arr) v)))'];
        yield 'php/ref' => ['(fn [m arr] (update m :k (fn [v] (php/preg_match "/a/" v (php/ref arr)))))'];
        yield 'yield' => ['(fn [m] (update m :k (fn [v] (php/yield v))))'];
    }

    public function test_a_local_named_update_keeps_its_own_call(): void
    {
        $php = $this->compileInBuildMode('(fn [m] (let [update (fn [m k f] m)] (update m :k (fn [v] v))))');

        self::assertStringContainsString('($update_', $php);
        self::assertStringNotContainsString('"assoc"', $php);
    }

    private function compileThreaded(int $depth): string
    {
        $steps = '';
        foreach (range(1, $depth) as $i) {
            $steps .= sprintf(' (update :k%d (fn [v] (php/+ v %d)))', $i, $i);
        }

        return $this->compileInBuildMode(sprintf('(fn [m] [(-> m%s)])', $steps));
    }

    private function compileNestedInBody(int $depth): string
    {
        $open = str_repeat('(update v :k (fn [v] ', $depth);
        $close = str_repeat('))', $depth);

        return $this->compileInBuildMode(sprintf('(fn [m] [(update m :k (fn [v] %s(php/+ v 1)%s))])', $open, $close));
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
