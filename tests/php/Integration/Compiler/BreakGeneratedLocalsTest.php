<?php

declare(strict_types=1);

namespace PhelTest\Integration\Compiler;

use Phel\Shared\CompileOptions;

use function ob_get_clean;
use function ob_start;
use function preg_match;

/**
 * `(break)` hides the locals a macro introduces and shows every binding the
 * user wrote, judged by how the symbol was made, not by its spelling.
 */
final class BreakGeneratedLocalsTest extends AbstractCompilerRuntimeTestCase
{
    private const string SOURCE = <<<'PHEL'
        (defmacro with-prefixed-gensym [& body] (let [t (gensym "tmp")] `(let [~t 1] ~@body)))
        (defmacro with-plain-gensym [& body] (let [t (gensym)] `(let [~t 2] ~@body)))
        (defmacro with-auto-gensym [& body] `(let [hidden# 3] ~@body))
        (fn [[head & tail]]
          (let [result__2 4 __phel_user 5]
            (with-prefixed-gensym (with-plain-gensym (with-auto-gensym (break))))))
        PHEL;

    public function test_break_lists_user_locals_only(): void
    {
        self::assertSame(
            ['head', 'tail', 'result__2', '__phel_user'],
            $this->breakpointLocalNames(self::SOURCE),
        );
    }

    public function test_break_hides_short_fn_params(): void
    {
        self::assertSame(['outer'], $this->breakpointLocalNames('(fn [outer] #(do (break) [%1 %2 %&]))'));
    }

    public function test_break_hides_locals_of_a_macro_defined_through_eval(): void
    {
        // `eval` is the path the REPL and nREPL take; the macro body is
        // emitted and run there, then expanded by a later compile.
        $this->compilerFacade->eval(
            '(defmacro with-eval-auto [& body] `(let [from-eval# 1] ~@body))',
            new CompileOptions(),
        );

        self::assertSame(['outer'], $this->breakpointLocalNames('(fn [outer] (with-eval-auto (break)))'));
    }

    /**
     * @return list<string>
     */
    private function breakpointLocalNames(string $source): array
    {
        ob_start();
        $code = $this->compilerFacade->compile(
            $source,
            new CompileOptions()->setIsEnabledSourceMaps(false),
        )->getPhpCode();
        ob_get_clean();

        self::assertSame(1, preg_match('/::breakpoint\([^(]+\((.*?)\)\)/s', $code, $map), $code);
        preg_match_all('/"([^"]+)",/', $map[1], $names);

        return $names[1];
    }
}
