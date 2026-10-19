<?php

declare(strict_types=1);

namespace PhelTest\Integration\Compiler\Analyzer;

use Phel\Shared\CompileOptions;
use PhelTest\Integration\Compiler\AbstractCompilerRuntimeTestCase;

/**
 * A tag naming a type of the current namespace must reach the generated
 * signature rooted: an evaluated form has no `namespace` line (#3654).
 */
final class OwnNamespaceTypeTagTest extends AbstractCompilerRuntimeTestCase
{
    public function test_a_tag_naming_a_struct_of_this_namespace_accepts_it(): void
    {
        $this->eval("(ns own-tag.fresh)\n(defstruct point [x])");

        self::assertSame(1, $this->eval("(ns own-tag.fresh)\n(defstruct point [x])\n((fn [^point p] (:x p)) (point 1))"));
    }

    /**
     * A namespace loaded from the compiled-code cache declares its classes
     * without the analyser seeing the `defstruct`, so a fn the REPL evaluates
     * in it next has only the loaded class to go by.
     */
    public function test_a_tag_naming_a_struct_loaded_without_analysis_accepts_it(): void
    {
        $this->eval("(ns own-tag.cached)\n(defstruct point [x])");
        self::$globalEnv->clearPhpClasses('own-tag.cached');

        self::assertSame(1, $this->eval('((fn [^point p] (:x p)) (own-tag.cached/point 1))'));
    }

    private function eval(string $phelCode): mixed
    {
        return $this->compilerFacade->eval($phelCode, new CompileOptions());
    }
}
