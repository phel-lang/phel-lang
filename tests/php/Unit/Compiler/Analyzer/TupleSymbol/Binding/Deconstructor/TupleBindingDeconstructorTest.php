<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\TupleSymbol\Binding\Deconstructor;

use Phel\Compiler\Analyzer\TypeAnalyzer\TupleSymbol\Binding\BindingValidatorInterface;
use Phel\Compiler\Analyzer\TypeAnalyzer\TupleSymbol\Binding\Deconstructor\TupleBindingDeconstructor;
use Phel\Compiler\Analyzer\TypeAnalyzer\TupleSymbol\Binding\TupleDeconstructor;
use Phel\Compiler\Exceptions\AbstractLocatedException;
use Phel\Lang\Symbol;
use Phel\Lang\Tuple;
use PHPUnit\Framework\TestCase;

final class TupleBindingDeconstructorTest extends TestCase
{
    private const REST_SYMBOL = TupleBindingDeconstructor::REST_SYMBOL_NAME;
    private const FIRST_SYMBOL = TupleBindingDeconstructor::FIRST_SYMBOL_NAME;
    private const NEXT_SYMBOL = TupleBindingDeconstructor::NEXT_SYMBOL_NAME;

    private TupleBindingDeconstructor $deconstructor;

    public function setUp(): void
    {
        Symbol::resetGen();

        $this->deconstructor = new TupleBindingDeconstructor(
            new TupleDeconstructor(
                $this->createMock(BindingValidatorInterface::class)
            )
        );
    }

    public function testEmptyTuple(): void
    {
        // Test for binding like this (let [[] x])
        // This will be destructured to this:
        // (let [__phel_1 x])
        $value = Symbol::create('x');
        $binding = Tuple::create();

        $bindings = [];
        $this->deconstructor->deconstruct($bindings, $binding, $value);

        self::assertEquals([
            [
                Symbol::create('__phel_1'),
                $value,
            ],
        ], $bindings);
    }

    public function testTupleWithOneSymbol(): void
    {
        // Test for binding like this (let [[a] x])
        // This will be destructured to this:
        // (let [__phel_1 x
        //       __phel_2 (first __phel_1)
        //       __phel_3 (next __phel_1)
        //       a __phel_2])

        $bindTo = Symbol::create('a');
        $value = Symbol::create('x');
        $binding = Tuple::create($bindTo);

        $bindings = [];
        $this->deconstructor->deconstruct($bindings, $binding, $value);

        self::assertEquals([
            [
                Symbol::create('__phel_1'),
                $value,
            ],
            [
                Symbol::create('__phel_2'),
                Tuple::create(
                    Symbol::create(self::FIRST_SYMBOL),
                    Symbol::create('__phel_1'),
                ),
            ],
            [
                Symbol::create('__phel_3'),
                Tuple::create(
                    Symbol::create(self::NEXT_SYMBOL),
                    Symbol::create('__phel_1'),
                ),
            ],
            [
                $bindTo,
                Symbol::create('__phel_2'),
            ],
        ], $bindings);
    }

    public function testTupleWithSymbols(): void
    {
        // Test for binding like this (let [[a b] x])
        // This will be destructured to this:
        // (let [__phel_1 x
        //       __phel_2 (first __phel_1)
        //       __phel_3 (next __phel_1)
        //       a __phel_2
        //       __phel_4 (first __phel_3)
        //       __phel_5 (next __phel_3)
        //       b __phel_4])
        $bindings = [];

        $bindToA = Symbol::create('a');
        $bindToB = Symbol::create('b');
        $value = Symbol::create('x');
        $binding = Tuple::create($bindToA, $bindToB);

        $this->deconstructor->deconstruct($bindings, $binding, $value);

        self::assertEquals([
            [
                Symbol::create('__phel_1'),
                $value,
            ],
            [
                Symbol::create('__phel_2'),
                Tuple::create(
                    Symbol::create(self::FIRST_SYMBOL),
                    Symbol::create('__phel_1'),
                ),
            ],
            [
                Symbol::create('__phel_3'),
                Tuple::create(
                    Symbol::create(self::NEXT_SYMBOL),
                    Symbol::create('__phel_1'),
                ),
            ],
            [
                $bindToA,
                Symbol::create('__phel_2'),
            ],
            [
                Symbol::create('__phel_4'),
                Tuple::create(
                    Symbol::create(self::FIRST_SYMBOL),
                    Symbol::create('__phel_3'),
                ),
            ],
            [
                Symbol::create('__phel_5'),
                Tuple::create(
                    Symbol::create(self::NEXT_SYMBOL),
                    Symbol::create('__phel_3'),
                ),
            ],
            [
                $bindToB,
                Symbol::create('__phel_4'),
            ],
        ], $bindings);
    }

    public function testTupleWithOneSymbolWithRest(): void
    {
        // Test for binding like this (let [[a & b] x])
        // This will be destructured to this:
        // (let [__phel_1 x
        //       __phel_2 (first __phel_1)
        //       __phel_3 (next __phel_1)
        //       a __phel_2
        //       __phel_4 __phel_3
        //       b __phel_4])

        $bindToA = Symbol::create('a');
        $bindToB = Symbol::create('b');
        $value = Symbol::create('x');
        $binding = Tuple::create(
            $bindToA,
            Symbol::create(self::REST_SYMBOL),
            $bindToB,
        );

        $bindings = [];
        $this->deconstructor->deconstruct($bindings, $binding, $value);

        self::assertEquals([
            [
                Symbol::create('__phel_1'),
                $value,
            ],
            [
                Symbol::create('__phel_2'),
                Tuple::create(
                    Symbol::create(self::FIRST_SYMBOL),
                    Symbol::create('__phel_1'),
                ),
            ],
            [
                Symbol::create('__phel_3'),
                Tuple::create(
                    Symbol::create(self::NEXT_SYMBOL),
                    Symbol::create('__phel_1'),
                ),
            ],
            [
                $bindToA,
                Symbol::create('__phel_2'),
            ],
            [
                Symbol::create('__phel_4'),
                Symbol::create('__phel_3'),
            ],
            [
                $bindToB,
                Symbol::create('__phel_4'),
            ],
        ], $bindings);
    }

    public function testExceptionWhenMultipleRestSymbol(): void
    {
        $bindToA = Symbol::create('a');
        $bindToB = Symbol::create('b');
        $value = Symbol::create('x');
        $binding = Tuple::create(
            $bindToA,
            Symbol::create(self::REST_SYMBOL),
            Symbol::create(self::REST_SYMBOL),
            $bindToB,
        );

        $this->expectException(AbstractLocatedException::class);
        $this->expectExceptionMessage('only one symbol can follow the & parameter');

        $bindings = [];
        $this->deconstructor->deconstruct($bindings, $binding, $value);
    }
}
