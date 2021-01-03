<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\TupleSymbol\Binding\Deconstructor;

use Phel\Compiler\Analyzer\TypeAnalyzer\TupleSymbol\Binding\BindingValidatorInterface;
use Phel\Compiler\Analyzer\TypeAnalyzer\TupleSymbol\Binding\Deconstructor\TableBindingDeconstructor;
use Phel\Compiler\Analyzer\TypeAnalyzer\TupleSymbol\Binding\TupleDeconstructor;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;
use Phel\Lang\Table;
use Phel\Lang\Tuple;
use PHPUnit\Framework\TestCase;

final class TableBindingDeconstructorTest extends TestCase
{
    private TableBindingDeconstructor $deconstructor;

    public function setUp(): void
    {
        Symbol::resetGen();

        $this->deconstructor = new TableBindingDeconstructor(
            new TupleDeconstructor(
                $this->createMock(BindingValidatorInterface::class)
            )
        );
    }

    public function testDeconstructTable(): void
    {
        // Test for binding like this (let [@{:key a} x])
        // This will be destructured to this:
        // (let [__phel_1 x
        //       __phel 2 (get __phel_1 :key)
        //       a __phel_2])

        $key = new Keyword('key');
        $bindTo = Symbol::create('a');
        $value = Symbol::create('x');
        $binding = Table::fromKVs($key, $bindTo);

        $bindings = [];
        $this->deconstructor->deconstruct($bindings, $binding, $value);

        self::assertEquals([
            // __phel_1 x
            [
                Symbol::create('__phel_1'),
                $value,
            ],
            // __phel 2 (get __phel_1 :key)
            [
                Symbol::create('__phel_2'),
                Tuple::create(
                    Symbol::create(Symbol::NAME_PHP_ARRAY_GET),
                    Symbol::create('__phel_1'),
                    $key
                ),
            ],
            // a __phel_2
            [
                $bindTo,
                Symbol::create('__phel_2'),
            ],
        ], $bindings);
    }

    public function testDeconstructTableNestedTuple(): void
    {
        // Test for binding like this (let [@{:key [a]} x])
        // This will be destructured to this:
        // (let [__phel_1 x
        //       __phel 2 (get __phel_1 :key)
        //       __phel_3 __phel_2
        //       __phel_3 __phel_2
        //       __phel_4 (first __phel_3)
        //       __phel_5 (next __phel_3)
        //       a __phel_4])

        $key = new Keyword('key');
        $bindTo = Symbol::create('a');
        $value = Symbol::create('x');
        $binding = Table::fromKVs($key, Tuple::create($bindTo));

        $bindings = [];
        $this->deconstructor->deconstruct($bindings, $binding, $value);

        self::assertEquals([
            // __phel_1 x
            [
                Symbol::create('__phel_1'),
                $value,
            ],
            // __phel 2 (get __phel_1 :key)
            [
                Symbol::create('__phel_2'),
                Tuple::create(
                    Symbol::create(Symbol::NAME_PHP_ARRAY_GET),
                    Symbol::create('__phel_1'),
                    $key
                ),
            ],
            // __phel_3 __phel_2
            [
                Symbol::create('__phel_3'),
                Symbol::create('__phel_2'),
            ],
            // __phel_4 (first __phel_3)
            [
                Symbol::create('__phel_4'),
                Tuple::create(
                    Symbol::create('first'),
                    Symbol::create('__phel_3'),
                ),
            ],
            // __phel_5 (next __phel_3)
            [
                Symbol::create('__phel_5'),
                Tuple::create(
                    Symbol::create('next'),
                    Symbol::create('__phel_3'),
                ),
            ],
            // a __phel_4
            [
                $bindTo,
                Symbol::create('__phel_4'),
            ],
        ], $bindings);
    }
}
