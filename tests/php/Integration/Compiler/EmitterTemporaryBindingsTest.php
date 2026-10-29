<?php

declare(strict_types=1);

namespace PhelTest\Integration\Compiler;

use Phel\Build\BuildFacade;
use Phel\Shared\CompileOptions;
use PHPUnit\Framework\Attributes\DataProvider;

final class EmitterTemporaryBindingsTest extends AbstractCompilerRuntimeTestCase
{
    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function scratchBindings(): iterable
    {
        yield 'truthiness parameter' => ['((fn [__truthy] (if (identity nil) 1 __truthy)) 5)', 5];
        yield 'munged truthiness parameter' => ['((fn [_-truthy] (if (identity nil) 1 _-truthy)) 5)', 5];
        yield 'or parameter' => ['((fn [__or] (or (identity nil) __or)) 5)', 5];
        yield 'constant parameter' => ['((fn [__phel_const_0] (do (identity [1 2 3]) __phel_const_0)) 5)', 5];
        yield 'call slot parameter' => ['((fn [__phel_call_0] (do (identity 1) __phel_call_0)) 5)', 5];
        yield 'guard parameter' => ['((fn [__phel_guard_0] (do (+ (identity 1) 2) __phel_guard_0)) 5)', 5];
        yield 'bare constant prefix parameter' => ['((fn [__phel_const] (do (identity [1]) (identity [2]) (identity [3]) __phel_const)) 5)', 5];
        yield 'variadic parameter' => ['((fn [& __phel_const_0] (do (identity [1 2 3]) (first __phel_const_0))) 5)', 5];
        yield 'typed parameter' => ['((fn [^float __truthy] (if (identity nil) 1.0 __truthy)) 5)', 5.0];
        yield 'loop binding' => ['((fn [] (loop [__phel_const 5] (identity [1]) (identity [2]) (+ __phel_const __phel_const))))', 10];
        yield 'munged loop binding' => ['((fn [] (loop [-_phel_const 5] (identity [1]) (identity [2]) (+ -_phel_const -_phel_const))))', 10];
        yield 'foreach binding' => ['((fn [] (let [r (atom nil)] (foreach [__phel_const [5]] (identity [1]) (identity [2]) (reset! r __phel_const)) (deref r))))', 5];
        yield 'generated parameter' => ['(defmacro colliding-fn [] (let [p (Phel.Lang.Symbol/createGenerated "__phel_const_0")] (list (symbol "fn") (vector p) (list (symbol "do") (list (symbol "identity") [1 2 3]) p)))) ((colliding-fn) 5)', 5];
        yield 'generated-looking user parameter' => ['((fn [__truthy __phel_1] (if (identity nil) 0 (+ __truthy __phel_1))) 2 3)', 5];
        yield 'catch binding' => ['(try (throw (Exception. "" 5)) (catch Exception __truthy (if (identity nil) 0 (.getCode __truthy))))', 5];
        yield 'nested parameter and loop' => ['((fn [__truthy] (let [r (atom nil)] (foreach [__truthy [2]] (reset! r (if (identity nil) 0 __truthy))) (+ __truthy (deref r)))) 5)', 7];
    }

    public function test_build_mode_call_slots_keep_the_parameter_value(): void
    {
        BuildFacade::enableBuildMode();
        try {
            self::assertSame(5, $this->compilerFacade->eval(
                '((fn [__phel_call_0] (do (identity 1) __phel_call_0)) 5)',
                new CompileOptions(),
            ));
        } finally {
            BuildFacade::disableBuildMode();
        }
    }

    #[DataProvider('scratchBindings')]
    public function test_emitter_temporaries_do_not_overwrite_phel_bindings(string $source, mixed $expected): void
    {
        self::assertSame($expected, $this->compilerFacade->eval($source, new CompileOptions()));
    }
}
