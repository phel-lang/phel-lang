<?php

declare(strict_types=1);

namespace PhelTest\Integration\Run\EvalNamespaceRequire;

use Phel;
use Phel\Build\BuildFacade;
use Phel\Compiler\CompilerFacade;
use Phel\Shared\CompileOptions;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

/**
 * Regression test for `(ns ... (:require ...))` evaluation outside the
 * interactive REPL (`phel eval`, nREPL). The emitted require code used to
 * resolve source directories only when `phel.core/*repl-mode*` was set, so a
 * plain eval session walked an empty dir list, never loaded the required
 * namespace, and the analyzer then failed with
 * `Cannot resolve symbol 'plus'` on the referring form.
 */
final class EvalNamespaceRequireTest extends TestCase
{
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_referred_symbol_resolves_without_repl_mode(): void
    {
        $this->bootEvalSession();

        $this->evalCode('(ns demo.test (:require eval-req-demo :refer [plus]))');

        self::assertSame(3, $this->evalCode('(plus 1 2)'));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_aliased_symbol_resolves_without_repl_mode(): void
    {
        $this->bootEvalSession();

        $this->evalCode('(ns demo.aliased-test (:require [eval-req-demo :as d]))');

        self::assertSame(3, $this->evalCode('(d/plus 1 2)'));
    }

    /**
     * Boots a plain eval session (as `phel eval` and `phel nrepl` run one):
     * phel.core loaded, but deliberately no `*repl-mode*` definition.
     */
    private function bootEvalSession(): void
    {
        Phel::bootstrap(__DIR__);

        new BuildFacade()->compileFile(
            __DIR__ . '/../../../../../src/phel/core.phel',
            tempnam(sys_get_temp_dir(), 'phel-core'),
        );
    }

    private function evalCode(string $phelCode): mixed
    {
        return new CompilerFacade()->eval($phelCode, new CompileOptions());
    }
}
