<?php

declare(strict_types=1);

namespace PhelTest\Integration\Compiler;

use DateTimeImmutable;
use Phel;
use Phel\Build\BuildFacade;
use Phel\Compiler\CompilerFacade;
use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\Keyword;
use Phel\Lang\Registry;
use Phel\Lang\Symbol;
use Phel\Run\RunFacade;
use Phel\Shared\CompileOptions;
use PHPUnit\Framework\Attributes\DataProvider;

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

    #[DataProvider('bindableCoreEqualities')]
    public function test_bindable_core_equality_keeps_runtime_dispatch(string $tag, string $source, bool $keyword): void
    {
        $original = Phel::getDefinition('phel.core', '=');
        $meta = Phel::getDefinitionMetaData('phel.core', '=');
        self::assertNotNull($meta);
        $bindableMeta = $meta->put(Keyword::create($tag), true);
        Phel::addDefinition('phel.core', '=', $original, $bindableMeta);
        BuildFacade::enableBuildMode();

        try {
            $function = $this->compiler->eval($source, new CompileOptions()->setOptimizationLevel(2));
            Phel::addDefinition('phel.core', '=', static fn(mixed ...$args): bool => false, $bindableMeta);

            self::assertFalse($function($keyword ? Keyword::create('a') : 1));
        } finally {
            Phel::addDefinition('phel.core', '=', $original, $meta);
            BuildFacade::disableBuildMode();
        }
    }

    public static function bindableCoreEqualities(): iterable
    {
        foreach (['redef', 'dynamic'] as $tag) {
            yield $tag . ' guarded int' => [$tag, '(fn [x] (= x 1))', false];
            yield $tag . ' typed keyword' => [$tag, '(fn [x] (= x :a))', true];
        }
    }

    public function test_core_function_values_use_slots_without_changing_identity(): void
    {
        BuildFacade::enableBuildMode();
        try {
            $output = $this->compileSnippet('(fn [] inc)');
            $function = $this->compiler->eval('(fn [] inc)', new CompileOptions()->setOptimizationLevel(2));
        } finally {
            BuildFacade::disableBuildMode();
        }

        self::assertStringContainsString('($__phel_call_0 ??= \\' . Registry::class . '::readRoot("phel.core", "inc"))', $output);
        self::assertStringNotContainsString('::fnSlot(', $output);
        self::assertSame(Phel::getDefinition('phel.core', 'inc'), $function());
        self::assertSame($function(), $function());
    }

    #[DataProvider('bindableCoreValues')]
    public function test_bindable_core_function_values_are_not_pinned(string $tag): void
    {
        $original = Phel::getDefinition('phel.core', 'inc');
        $meta = Phel::getDefinitionMetaData('phel.core', 'inc');
        self::assertNotNull($meta);
        $bindableMeta = $meta->put(Keyword::create($tag), true);
        Phel::addDefinition('phel.core', 'inc', $original, $bindableMeta);
        BuildFacade::enableBuildMode();

        try {
            $function = $this->compiler->eval('(fn [] inc)', new CompileOptions()->setOptimizationLevel(2));
            self::assertSame($original, $function());
            $replacement = static fn(int $x): int => $x;
            Phel::addDefinition('phel.core', 'inc', $replacement, $bindableMeta);

            self::assertSame($replacement, $function());
        } finally {
            Phel::addDefinition('phel.core', 'inc', $original, $meta);
            BuildFacade::disableBuildMode();
        }
    }

    public static function bindableCoreValues(): iterable
    {
        yield 'redef' => ['redef'];
        yield 'dynamic' => ['dynamic'];
    }

    public function test_a_cached_core_function_value_keeps_foreign_callable_identity(): void
    {
        $original = Phel::getDefinition('phel.core', 'inc');
        $meta = Phel::getDefinitionMetaData('phel.core', 'inc');
        $replacement = static fn(int $x): int => $x + 1;
        Phel::addDefinition('phel.core', 'inc', $replacement, $meta);
        BuildFacade::enableBuildMode();

        try {
            $function = $this->compiler->eval('(fn [] inc)', new CompileOptions()->setOptimizationLevel(2));

            self::assertSame($replacement, $function());
            self::assertSame($replacement, $function());
        } finally {
            Phel::addDefinition('phel.core', 'inc', $original, $meta);
            BuildFacade::disableBuildMode();
        }
    }

    public function test_a_core_function_value_still_reads_the_root_outside_build_mode(): void
    {
        BuildFacade::disableBuildMode();
        $original = Phel::getDefinition('phel.core', 'inc');
        $meta = Phel::getDefinitionMetaData('phel.core', 'inc');

        try {
            $function = $this->compiler->eval('(fn [] inc)', new CompileOptions()->setOptimizationLevel(2));
            self::assertSame($original, $function());
            $replacement = static fn(int $x): int => $x;
            Phel::addDefinition('phel.core', 'inc', $replacement, $meta);

            self::assertSame($replacement, $function());
        } finally {
            Phel::addDefinition('phel.core', 'inc', $original, $meta);
        }
    }

    public function test_core_function_values_passed_to_higher_order_calls_match_the_runtime(): void
    {
        BuildFacade::enableBuildMode();
        try {
            $function = $this->compiler->eval('(fn [xs] (reduce + 0 (filter odd? xs)))', new CompileOptions()->setOptimizationLevel(2));
        } finally {
            BuildFacade::disableBuildMode();
        }

        $values = Phel::vector([-3, -2, -1, 0, 1, 2, 3]);
        $reduce = Phel::getDefinition('phel.core', 'reduce');
        $filter = Phel::getDefinition('phel.core', 'filter');
        $odd = Phel::getDefinition('phel.core', 'odd?');
        $plus = Phel::getDefinition('phel.core', '+');

        self::assertSame($reduce($plus, 0, $filter($odd, $values)), $function($values));
    }

    public function test_a_square_checks_its_local_integer_type_once(): void
    {
        BuildFacade::enableBuildMode();
        try {
            $output = $this->compileSnippet('(fn [x] (* x x))');
            $effects = $this->compileSnippet('(fn [f] (* (f) (f)))');
        } finally {
            BuildFacade::disableBuildMode();
        }

        self::assertSame(1, substr_count($output, '\\is_int($x)'));
        self::assertSame(2, substr_count($effects, '($f)()'));
    }

    public function test_parity_calls_guard_native_integer_bits(): void
    {
        BuildFacade::enableBuildMode();
        try {
            $output = $this->compileSnippet('(fn [x] [(even? x) (odd? x)])');
        } finally {
            BuildFacade::disableBuildMode();
        }

        self::assertStringContainsString('($x & 1) === 0', $output);
        self::assertStringContainsString('($x & 1) === 1', $output);
        self::assertSame(2, substr_count($output, '\\is_int($x)'));
    }

    public function test_a_caught_exception_uses_instance_method_dispatch(): void
    {
        $output = $this->compileSnippet('(fn [f] (try (f) (catch \\Exception e (.getMessage e))))');

        self::assertStringNotContainsString('is_string', $output);
        self::assertMatchesRegularExpression('/\$e_\d+->getMessage\(\)/', $output);
    }

    public function test_a_caught_exception_stays_known_inside_a_stable_function_argument(): void
    {
        $output = $this->compileSnippet('(fn [f] (try (f) (catch \\Exception e (println (.getMessage e)))))');

        self::assertStringNotContainsString('is_string', $output);
        self::assertMatchesRegularExpression('/\$e_\d+->getMessage\(\)/', $output);
    }

    #[DataProvider('mutableCatchBodies')]
    public function test_a_mutated_catch_binding_keeps_runtime_dispatch(string $body): void
    {
        $this->compiler->eval('(defn replace-caught [^:by-ref x] (php/sscanf "DateTimeImmutable" "%s" (php/ref x)))');
        Symbol::resetGen();
        $function = $this->compiler->eval(
            '(fn [] (try (throw (new \\Exception "caught"))'
            . ' (catch \\Exception e ' . $body . ')))',
            new CompileOptions()->setOptimizationLevel(2),
        );

        self::assertSame(DateTimeImmutable::getLastErrors(), $function());
    }

    public static function mutableCatchBodies(): iterable
    {
        yield 'explicit PHP reference' => ['(php/sscanf "DateTimeImmutable" "%s" (php/ref e)) (.getLastErrors e)'];
        yield 'reference in a captured closure' => ['((fn [] (php/sscanf "DateTimeImmutable" "%s" (php/ref e)) (.getLastErrors e)))'];
        yield 'Phel function with a reference parameter' => ['(replace-caught e) (.getLastErrors e)'];
        yield 'direct PHP assignment' => ['(php/= e "DateTimeImmutable") (.getLastErrors e)'];
        yield 'PHP eval' => ['(php/eval "$e_1 = \'DateTimeImmutable\';") (.getLastErrors e)'];
        yield 'PHP extract' => ['(php/extract (php-associative-array "e_1" "DateTimeImmutable")) (.getLastErrors e)'];
        yield 'vector metadata' => ['(let [v ^{:touch (php/= e "DateTimeImmutable")} []] (php/count v)) (.getLastErrors e)'];
        yield 'set metadata' => ['(let [v ^{:touch (php/= e "DateTimeImmutable")} #{}] (php/count v)) (.getLastErrors e)'];
        yield 'map metadata' => ['(let [v ^{:touch (php/= e "DateTimeImmutable")} {}] (php/count v)) (.getLastErrors e)'];
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

    /**
     * A slot is filled on the first call, which may run inside a `binding`
     * frame. A bindable callee therefore gets no slot, or the bound value
     * outlives the frame (#3367).
     */
    public function test_dynamic_callee_keeps_its_binding_scoped(): void
    {
        BuildFacade::enableBuildMode();
        try {
            $result = new RunFacade()->eval(
                '(do (defn ^:dynamic dyn-fn ([a] (str "base:" a)) ([a b] (str "base2:" a b)))'
                . ' (def call-it (fn [x] (dyn-fn x 1)))'
                . ' [(binding [dyn-fn (fn [a b] (str "bound:" a b))] (call-it "in")) (call-it "out")])',
            );
        } finally {
            BuildFacade::disableBuildMode();
        }

        self::assertSame(['bound:in1', 'base2:out1'], $result instanceof PersistentVectorInterface ? $result->toArray() : $result);
    }

    public function test_bindable_callees_get_no_call_slot(): void
    {
        BuildFacade::enableBuildMode();
        $output = $this->compileSnippet(
            '(defn ^:dynamic dyn-fn [x] x) (defn ^:redef redef-fn [x] x) (fn [x] [(dyn-fn x) (redef-fn x)])',
        );
        BuildFacade::disableBuildMode();

        $phel = '\\' . Phel::class;

        self::assertStringNotContainsString('$__phel_call_', $output);
        self::assertStringContainsString('(' . $phel . '::getDefinition("user", "dyn-fn"))', $output);
        self::assertStringContainsString('(' . $phel . '::getDefinition("user", "redef-fn"))', $output);
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
