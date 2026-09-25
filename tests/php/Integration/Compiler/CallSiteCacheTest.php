<?php

declare(strict_types=1);

namespace PhelTest\Integration\Compiler;

use Phel;
use Phel\Build\BuildFacade;
use Phel\Compiler\CompilerFacade;
use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;
use Phel\Lang\Registry;
use Phel\Lang\Symbol;
use Phel\Run\RunFacade;
use Phel\Shared\CompileOptions;
use PHPUnit\Framework\TestCase;

use function array_map;
use function implode;
use function range;
use function strlen;
use function substr_count;

final class CallSiteCacheTest extends TestCase
{
    private CompilerFacade $compiler;

    protected function setUp(): void
    {
        Phel::bootstrap(__DIR__);
        Symbol::resetGen();
        GlobalEnvironmentSingleton::initializeNew();

        // The compiler routes call-site caching off the runtime registry
        // entry `phel.core/*build-mode*`, so the production bootstrap must
        // have loaded core before either mode is exercised.
        new BuildFacade()->compileFile(
            __DIR__ . '/../../../../src/phel/core.phel',
            tempnam(sys_get_temp_dir(), 'phel-core'),
        );

        $this->compiler = new CompilerFacade();
    }

    public function test_build_mode_on_hoists_call_sites_to_static_slots(): void
    {
        // Use a vector so both `+` call sites are live (the simplification
        // pass drops pure non-tail expressions, which would otherwise
        // remove the first call before the cache could see it).
        BuildFacade::enableBuildMode();
        $output = $this->compileSnippet('(fn [x] [(+ x 1) (+ x 2)])');
        BuildFacade::disableBuildMode();

        $phel = '\\' . Phel::class;

        // `+` is multi-arity with a fixed two-argument arity, so each slot is
        // filled through `fnSlot` and called on `invokeArity2` (#3354).
        self::assertStringContainsString('static $__phel_call_0', $output);
        self::assertStringContainsString('($__phel_call_0 ??= ' . $phel . '::fnSlot("phel.core", "+"))->invokeArity2($x, 1)', $output);
        self::assertStringContainsString('($__phel_call_1 ??= ' . $phel . '::fnSlot("phel.core", "+"))->invokeArity2($x, 2)', $output);
    }

    public function test_single_arity_callee_keeps_its_positional_invoke(): void
    {
        BuildFacade::enableBuildMode();
        $output = $this->compileSnippet('(defn one-arity [x] x) (fn [x] (one-arity x))');
        BuildFacade::disableBuildMode();

        $phel = '\\' . Phel::class;

        self::assertStringContainsString('($__phel_call_0 ??= ' . $phel . '::getDefinition("user", "one-arity"))->__invoke($x)', $output);
    }

    public function test_count_that_hits_the_variadic_arm_keeps_invoke(): void
    {
        BuildFacade::enableBuildMode();
        $output = $this->compileSnippet('(defn va ([a] a) ([a b & more] more)) (fn [x] [(va x) (va x x x)])');
        BuildFacade::disableBuildMode();

        self::assertStringContainsString('::fnSlot("user", "va"))->invokeArity1($x)', $output);
        self::assertStringContainsString('::getDefinition("user", "va"))->__invoke($x, $x, $x)', $output);
    }

    /**
     * The shortcut used to write the arguments in both arms of an
     * `instanceof` ternary, so each nested level doubled the output: twelve
     * `get` levels came to megabytes (#3354).
     */
    public function test_nested_shortcut_calls_emit_linear_code(): void
    {
        $levels = implode(' ', array_map(static fn(int $i): string => '(get :k' . $i . ' {})', range(1, 12)));

        BuildFacade::enableBuildMode();
        $output = $this->compileSnippet('(fn [m] (-> m ' . $levels . '))');
        BuildFacade::disableBuildMode();

        self::assertSame(12, substr_count($output, '->invokeArity3('));
        self::assertLessThan(4000, strlen($output));
    }

    public function test_global_redefined_to_a_non_fn_is_still_called(): void
    {
        BuildFacade::enableBuildMode();
        try {
            $result = new RunFacade()->eval(
                '(do (defn two-ways ([m] m) ([m d] d))'
                . ' (def call-it (fn [m] (two-ways m :nf)))'
                . ' (def two-ways :x)'
                . ' (call-it {:x 5}))',
            );
        } finally {
            BuildFacade::disableBuildMode();
        }

        self::assertSame(5, $result);
    }

    public function test_build_mode_off_skips_cache_so_repl_redefine_still_wins(): void
    {
        BuildFacade::disableBuildMode();
        $output = $this->compileSnippet('(fn [x] [(+ x 1) (+ x 2)])');

        $registry = '\\' . Registry::class;

        self::assertStringNotContainsString('$__phel_call_', $output);
        // Uncached, so each call reads the definition afresh and a REPL
        // redefine wins. `+` carries no `^:dynamic`/`^:redef`, so the read is
        // the registry root (#3179); what matters here is that it is a read
        // per call rather than a pinned slot.
        self::assertStringContainsString('(' . $registry . '::readRoot("phel.core", "+"))->__invoke($x, 1)', $output);
        self::assertStringContainsString('(' . $registry . '::readRoot("phel.core", "+"))->__invoke($x, 2)', $output);
    }

    public function test_call_method_dispatch_targets_only_known_fn_defs(): void
    {
        BuildFacade::enableBuildMode();
        $output = $this->compileSnippet('(def k :a) (k {:a 1})');
        BuildFacade::disableBuildMode();

        $registry = '\\' . Registry::class;

        // `k` resolves to a Keyword (callable via __invoke), but the
        // analyzer has no `arglists` meta for it, so we keep the legacy
        // magic-dispatch form rather than risk `->__invoke` on a non-AbstractFn.
        self::assertStringContainsString('(' . $registry . '::readRoot("user", "k"))(', $output);
        self::assertStringNotContainsString('(' . $registry . '::readRoot("user", "k"))->__invoke', $output);
    }

    public function test_global_redefined_to_a_non_callable_names_its_type(): void
    {
        BuildFacade::enableBuildMode();
        try {
            $this->expectExceptionMessage('Cannot call a value of type int');
            new RunFacade()->eval(
                '(do (defn not-for-long ([m] m) ([m d] d))'
                . ' (def call-it (fn [m] (not-for-long m 1)))'
                . ' (def not-for-long 5)'
                . ' (call-it 0))',
            );
        } finally {
            BuildFacade::disableBuildMode();
        }
    }

    private function compileSnippet(string $phel): string
    {
        GlobalEnvironmentSingleton::getInstance()->setNs('user');
        Symbol::resetGen();

        $options = new CompileOptions()
            ->setSource(self::class);

        return $this->compiler->compile($phel, $options)->getPhpCode();
    }
}
