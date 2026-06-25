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

/**
 * End-to-end guard for issue #2622: at optimization level 2 the
 * {@see Phel\Compiler\Domain\Analyzer\TypeAnalyzer\Simplification\CallInliner}
 * inlines a `^:pure` let-bodied `defn` whose body contains a nested call
 * that lowers to an IIFE (`or`/`and`/`cond`). The renamed parameter shadow
 * must be captured by that nested IIFE's `use(...)` clause; otherwise it
 * reads as an undefined variable and arithmetic on the resulting `nil`
 * crashes at runtime.
 */
final class CallInlinerCaptureTest extends TestCase
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

    public function test_nested_iife_captures_renamed_param_shadow(): void
    {
        $opt2 = new CompileOptions()->setSource('inliner-capture')->setOptimizationLevel(2);

        BuildFacade::enableBuildMode();
        try {
            $this->compilerFacade->compile($this->program(), $opt2);
        } finally {
            BuildFacade::disableBuildMode();
        }

        // A correctly-captured enemy resolves `(:type enemy)` to `:goblin`,
        // finds `{:range 5}` in caster-spec and reports a hit at distance 5.
        // A dropped capture would read `nil`, fall back to the default
        // range and (in the reporter's case) crash on nil arithmetic.
        $hit = $this->compilerFacade->eval(
            '(call-site {:type :goblin :x 0 :y 0} 3.0 4.0)',
            new CompileOptions()->setSource('inliner-capture-eval')->setOptimizationLevel(2),
        );

        self::assertEquals(Keyword::create('hit'), $hit);
    }

    private function program(): string
    {
        return <<<'PHEL'
        (def caster-spec {:goblin {:range 5}})
        (def attack-spec {})
        (def default-attack-spec {:range 1})

        (defn ^:pure attack-spec-of [enemy]
          (or (get caster-spec (:type enemy))
              (get attack-spec (:type enemy))
              default-attack-spec))

        (defn ^:pure in-attack-range? [enemy ^float px ^float py]
          (let [dx (php/- px (:x enemy))
                dy (php/- py (:y enemy))
                r  (:range (attack-spec-of enemy))]
            (php/<= (php/+ (php/* dx dx) (php/* dy dy)) (php/* r r))))

        (defn call-site [enemy px py]
          (if (in-attack-range? enemy px py) :hit :miss))
        PHEL;
    }
}
