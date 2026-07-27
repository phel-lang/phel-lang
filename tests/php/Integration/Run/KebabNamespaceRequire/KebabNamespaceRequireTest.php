<?php

declare(strict_types=1);

namespace PhelTest\Integration\Run\KebabNamespaceRequire;

use Phel;
use Phel\Build\BuildFacade;
use Phel\Compiler\CompilerFacade;
use Phel\Shared\CompileOptions;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

/**
 * The `(ns ... (:require ...))` form emitted for REPL/eval mode skips a
 * dependency that is already in the runtime registry. That check compared the
 * canonical Phel namespace (`kebabns.kebab-lib`) against registry keys, which
 * are munged (`kebabns.kebab_lib`), so every kebab-case namespace looked
 * unloaded: its file was re-evaluated, re-running its top level and wiping any
 * state it had set up. Plain namespaces were unaffected, which is what kept
 * this hidden.
 */
final class KebabNamespaceRequireTest extends TestCase
{
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_a_kebab_case_namespace_is_evaluated_once_across_repeated_requires(): void
    {
        $this->bootRepl();

        $this->evalCode('(ns first-user (:require kebabns.kebab-lib))');
        $this->evalCode('(ns second-user (:require kebabns.kebab-lib))');

        self::assertSame(
            1,
            LoadCounter::countFor('kebabns.kebab-lib'),
            'The second require must find the namespace already loaded.',
        );
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_a_plain_namespace_keeps_being_evaluated_once(): void
    {
        $this->bootRepl();

        $this->evalCode('(ns first-user (:require kebabns.plainlib))');
        $this->evalCode('(ns second-user (:require kebabns.plainlib))');

        self::assertSame(1, LoadCounter::countFor('kebabns.plainlib'));
    }

    /**
     * The `phel eval` and nREPL case from #2886: a project namespace has to load
     * from the configured source directories even though no REPL is running, so
     * a definition it exports resolves at the call site.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_a_project_namespace_loads_outside_a_repl_session(): void
    {
        $this->bootRepl();

        $this->evalCode('(ns consumer (:require kebabns.plainlib))');

        self::assertSame(
            1,
            LoadCounter::countFor('kebabns.plainlib'),
            'A project namespace must load without `*repl-mode*` being set.',
        );
        self::assertSame(
            'hello',
            $this->evalCode('(kebabns.plainlib/greet)'),
            'The required namespace must export its definitions to the caller.',
        );
    }

    /**
     * No `*repl-mode*` is set here on purpose. The emitted require code used to
     * resolve source directories only when that flag was on, which only `phel
     * repl` sets, so `phel eval` and the nREPL server walked an empty directory
     * list and loaded nothing (#2886). Booting without the flag is what keeps
     * that fixed: if the gate returns, every test in this class fails.
     */
    private function bootRepl(): void
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
