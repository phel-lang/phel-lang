<?php

declare(strict_types=1);

namespace PhelTest\Integration\Compiler\Analyzer;

use PhelTest\Integration\Compiler\AbstractCompilerRuntimeTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class UseAliasShadowingTest extends AbstractCompilerRuntimeTestCase
{
    #[DataProvider('contexts')]
    public function test_imports_keep_their_class_context_while_own_vars_win_values(string $context, string $expression, bool $shadow): void
    {
        $namespace = 'use-shadow.' . $context . ($shadow ? '.own' : '.alias');
        $source = '(ns ' . $namespace . ' (:use DateTimeImmutable :as Date))'
            . ($shadow ? '(def Date 1)' : '') . $expression;
        $value = $this->compilerFacade->eval($source);

        self::assertSame($context === 'value' && $shadow ? 1 : 'DateTimeImmutable', $value);
    }

    public static function contexts(): iterable
    {
        foreach ([
            'value' => '(identity Date)',
            'new' => '(php/get_class (new Date "2020-01-01"))',
            'static' => '(php/get_class (.createFromFormat Date "!Y-m-d" "2020-01-01"))',
            'callable' => '(php/get_class ((php/callable Date createFromFormat) "!Y-m-d" "2020-01-01"))',
            'qualified-callable' => '(let [f Date/createFromFormat] (php/get_class (f "!Y-m-d" "2020-01-01")))',
            'tag' => '((fn [^Date x] (php/get_class x)) (DateTimeImmutable. "2020-01-01"))',
        ] as $context => $expression) {
            yield $context . ' alias only' => [$context, $expression, false];
            yield $context . ' with own binding' => [$context, $expression, true];
        }
    }

    public function test_an_own_callable_wins_over_an_import_alias(): void
    {
        self::assertSame(42, $this->compilerFacade->eval('(ns use-shadow.call (:use DateTimeImmutable :as Date)) (def Date (fn [] 42)) (Date)'));
    }

    public function test_a_lexical_local_remains_a_dynamic_constructor_target(): void
    {
        self::assertSame('stdClass', $this->compilerFacade->eval('(ns use-shadow.local (:use DateTimeImmutable :as Date)) (def Date 1) (let [Date "stdClass"] (php/get_class (new Date)))'));
    }

    public function test_qualified_names_keep_their_namespace_despite_an_own_var_and_import(): void
    {
        $value = $this->compilerFacade->eval('(ns use-shadow.qualified.other) (def Date 2) (ns use-shadow.qualified.main (:use DateTimeImmutable :as Date)) (def Date 1) [Date use-shadow.qualified.other/Date]');

        self::assertSame([1, 2], $value->toArray());
    }

    public function test_a_lexical_callable_wins_over_own_vars_and_imports(): void
    {
        self::assertSame(3, $this->compilerFacade->eval('(ns use-shadow.local-call (:use DateTimeImmutable :as Date)) (def Date 1) (let [Date (fn [] 3)] (Date))'));
    }
}
