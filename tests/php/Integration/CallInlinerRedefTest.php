<?php

declare(strict_types=1);

namespace PhelTest\Integration;

use Phel;
use Phel\Build\BuildFacade;
use Phel\Compiler\CompilerFacade;
use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;
use Phel\Shared\CompileOptions;
use PHPUnit\Framework\TestCase;

use function sprintf;

/**
 * `^:redef` opts a definition out of inlining so its call sites keep reading
 * the global.
 *
 * Inlining splices the callee's body into the caller, which leaves nothing for
 * `with-redefs` or `phel.mock` to intercept. That is inherent to direct
 * linking rather than a defect in it, and it is why `-O2` used to fail two
 * `phel.mock` tests while every check stayed green (#3126). Clojure answers the
 * same tension the same way, with a per-definition opt-out.
 *
 * What is pinned here is the guarantee: a `^:redef` callee still observes a
 * rebinding at `-O2`, and nothing changes at the default level. The converse,
 * that an ordinary callee is inlined past `with-redefs`, is deliberately not
 * asserted here: whether a given call site inlines depends on its position, and
 * a test that pinned one such site would pin the inliner's current shape rather
 * than this contract. `tests/phel/mock.phel` is the real evidence for it. Those
 * tests failed at `-O2` before `^:redef` existed and pass with it, and the
 * `Core tests at -O2` CI job is what keeps that true.
 */
final class CallInlinerRedefTest extends TestCase
{
    private CompilerFacade $compilerFacade;

    protected function setUp(): void
    {
        Phel::bootstrap(__DIR__);
        Symbol::resetGen();
        GlobalEnvironmentSingleton::initializeNew();
        new BuildFacade()->compileFile(
            __DIR__ . '/../../../src/phel/core.phel',
            tempnam(sys_get_temp_dir(), 'phel-core'),
        );
        $this->compilerFacade = new CompilerFacade();
    }

    public function test_a_redef_annotated_callee_still_sees_with_redefs_at_level_two(): void
    {
        $this->compileAtLevelTwo($this->program());

        self::assertEquals(
            Keyword::create('redefined'),
            $this->evalAtLevelTwo('mockable-result'),
        );
    }

    /**
     * At the default level nothing is inlined, so both spellings observe the
     * rebinding. `^:redef` costs nothing here and changes nothing.
     */
    public function test_both_see_with_redefs_at_the_default_level(): void
    {
        $opt0 = new CompileOptions()->setSource('inliner-redef-0');

        BuildFacade::enableBuildMode();
        try {
            $this->compilerFacade->compile($this->program(), $opt0);
        } finally {
            BuildFacade::disableBuildMode();
        }

        foreach (['plain-result', 'mockable-result'] as $name) {
            self::assertEquals(
                Keyword::create('redefined'),
                $this->compilerFacade->eval(
                    $name,
                    new CompileOptions()->setSource('inliner-redef-0-eval'),
                ),
                sprintf('%s observes the rebinding at level 0', $name),
            );
        }
    }

    private function compileAtLevelTwo(string $program): void
    {
        $opt2 = new CompileOptions()->setSource('inliner-redef')->setOptimizationLevel(2);

        BuildFacade::enableBuildMode();
        try {
            $this->compilerFacade->compile($program, $opt2);
        } finally {
            BuildFacade::disableBuildMode();
        }
    }

    private function evalAtLevelTwo(string $code): mixed
    {
        return $this->compilerFacade->eval(
            $code,
            new CompileOptions()->setSource('inliner-redef-eval')->setOptimizationLevel(2),
        );
    }

    private function program(): string
    {
        return <<<'PHEL'
        (defn plain [x] :original)
        (defn ^:redef mockable [x] :original)

        ;; Two things this shape has to get right. The `with-redefs` must sit in
        ;; the same compilation unit as the definition it rebinds, because that
        ;; is the only time the inliner can see the callee. And the call must
        ;; not be in return position inside a `defn`, which the inliner declines
        ;; for its own reasons (#3125), so it would pass here without proving
        ;; anything about `^:redef`.
        (def plain-result (with-redefs [plain (fn [x] :redefined)] (plain 1)))
        (def mockable-result (with-redefs [mockable (fn [x] :redefined)] (mockable 1)))
        PHEL;
    }
}
