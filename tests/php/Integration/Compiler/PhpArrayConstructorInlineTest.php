<?php

declare(strict_types=1);

namespace PhelTest\Integration\Compiler;

use Phel\Compiler\Domain\Evaluator\Exceptions\EvaluatedCodeException;

final class PhpArrayConstructorInlineTest extends AbstractCompilerRuntimeTestCase
{
    public function test_direct_indexed_constructor_skips_global_dispatch(): void
    {
        $php = $this->compilerFacade->compile('(def xs (php-indexed-array 1 2 3))')->getPhpCode();

        self::assertStringNotContainsString('"php-indexed-array"', $php);
        self::assertSame([1, 2, 3], $this->compilerFacade->eval('(php-indexed-array 1 2 3)'));
    }

    public function test_direct_associative_constructor_skips_global_dispatch(): void
    {
        $php = $this->compilerFacade->compile(
            '(def attrs (php-associative-array "name" "Phel" "stable" true))',
        )->getPhpCode();

        self::assertStringNotContainsString('"php-associative-array"', $php);
        self::assertSame(
            ['name' => 'Phel', 'stable' => true],
            $this->compilerFacade->eval('(php-associative-array "name" "Phel" "stable" true)'),
        );
    }

    public function test_apply_keeps_both_constructors_callable(): void
    {
        $indexedPhp = $this->compilerFacade->compile('(apply php-indexed-array [1 2 3])')->getPhpCode();
        $associativePhp = $this->compilerFacade
            ->compile('(apply php-associative-array ["a" 1 "b" 2])')
            ->getPhpCode();

        self::assertStringContainsString('"php-indexed-array"', $indexedPhp);
        self::assertStringContainsString('"php-associative-array"', $associativePhp);
        self::assertSame([1, 2, 3], $this->compilerFacade->eval('(apply php-indexed-array [1 2 3])'));
        self::assertSame(
            ['a' => 1, 'b' => 2],
            $this->compilerFacade->eval('(apply php-associative-array ["a" 1 "b" 2])'),
        );
    }

    public function test_odd_associative_arity_keeps_runtime_validation(): void
    {
        $php = $this->compilerFacade
            ->compile('(defn invalid-attrs [] (php-associative-array "a" 1 "b"))')
            ->getPhpCode();

        self::assertStringContainsString('"php-associative-array"', $php);

        $this->expectException(EvaluatedCodeException::class);
        $this->expectExceptionMessage(
            "An even number of parameters must be provided for 'php-associative-array'",
        );

        $this->compilerFacade->eval('(php-associative-array "a" 1 "b")');
    }

    public function test_inline_arguments_run_once_in_left_to_right_order(): void
    {
        $this->compilerFacade->eval('(def constructor-log (atom []))');

        $indexed = $this->compilerFacade->eval(
            '(php-indexed-array (do (swap! constructor-log conj :first) 1)'
            . ' (do (swap! constructor-log conj :second) 2))',
        );
        $associative = $this->compilerFacade->eval(
            '(php-associative-array'
            . ' (do (swap! constructor-log conj :third) "key")'
            . ' (do (swap! constructor-log conj :fourth) "value"))',
        );

        self::assertSame([1, 2], $indexed);
        self::assertSame(['key' => 'value'], $associative);
        self::assertTrue(
            $this->compilerFacade->eval('(= [:first :second :third :fourth] @constructor-log)'),
        );
    }
}
