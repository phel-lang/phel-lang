<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\SpecialForm\Binding\Deconstructor;

use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\Binding\BindingValidatorInterface;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\Binding\Deconstructor;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\Binding\Deconstructor\VectorBindingDeconstructor;
use Phel\Compiler\Domain\Exceptions\AbstractLocatedException;
use Phel\Lang\Symbol;
use Phel\Lang\Type;
use PHPUnit\Framework\TestCase;

final class VectorBindingDeconstructorTest extends TestCase
{
    private const string REST_SYMBOL = VectorBindingDeconstructor::REST_SYMBOL_NAME;

    private const string FIRST_SYMBOL = VectorBindingDeconstructor::FIRST_SYMBOL_NAME;

    private const string NEXT_SYMBOL = VectorBindingDeconstructor::NEXT_SYMBOL_NAME;

    private VectorBindingDeconstructor $deconstructor;

    protected function setUp(): void
    {
        Symbol::resetGen();

        $this->deconstructor = new VectorBindingDeconstructor(
            new Deconstructor(
                $this->createMock(BindingValidatorInterface::class),
            ),
        );
    }

    public function test_empty_vector(): void
    {
        // Test for binding like this (let [[] x])
        // This will be destructured to this:
        // (let [__phel_1 x])
        $value = Symbol::create('x');
        $binding = Type::persistentListFromArray([]);

        $bindings = [];
        $this->deconstructor->deconstruct($bindings, $binding, $value);

        self::assertEquals([
            [
                Symbol::create('__phel_1'),
                $value,
            ],
        ], $bindings);
    }

    public function test_vector_with_one_symbol(): void
    {
        // Test for binding like this (let [[a] x])
        // This will be destructured to this:
        // (let [__phel_1 x
        //       __phel_2 (first __phel_1)
        //       __phel_3 (next __phel_1)
        //       a __phel_2])

        $bindTo = Symbol::create('a');
        $value = Symbol::create('x');
        $binding = Type::persistentListFromArray([$bindTo]);

        $bindings = [];
        $this->deconstructor->deconstruct($bindings, $binding, $value);

        self::assertEquals([
            [
                Symbol::create('__phel_1'),
                $value,
            ],
            [
                Symbol::create('__phel_2'),
                Type::persistentListFromArray([
                    Symbol::create(self::FIRST_SYMBOL),
                    Symbol::create('__phel_1'),
                ]),
            ],
            [
                Symbol::create('__phel_3'),
                Type::persistentListFromArray([
                    Symbol::create(self::NEXT_SYMBOL),
                    Symbol::create('__phel_1'),
                ]),
            ],
            [
                $bindTo,
                Symbol::create('__phel_2'),
            ],
        ], $bindings);
    }

    public function test_vector_with_symbols(): void
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
        $binding = Type::persistentVectorFromArray([$bindToA, $bindToB]);

        $this->deconstructor->deconstruct($bindings, $binding, $value);

        self::assertEquals([
            [
                Symbol::create('__phel_1'),
                $value,
            ],
            [
                Symbol::create('__phel_2'),
                Type::persistentListFromArray([
                    Symbol::create(self::FIRST_SYMBOL),
                    Symbol::create('__phel_1'),
                ]),
            ],
            [
                Symbol::create('__phel_3'),
                Type::persistentListFromArray([
                    Symbol::create(self::NEXT_SYMBOL),
                    Symbol::create('__phel_1'),
                ]),
            ],
            [
                $bindToA,
                Symbol::create('__phel_2'),
            ],
            [
                Symbol::create('__phel_4'),
                Type::persistentListFromArray([
                    Symbol::create(self::FIRST_SYMBOL),
                    Symbol::create('__phel_3'),
                ]),
            ],
            [
                Symbol::create('__phel_5'),
                Type::persistentListFromArray([
                    Symbol::create(self::NEXT_SYMBOL),
                    Symbol::create('__phel_3'),
                ]),
            ],
            [
                $bindToB,
                Symbol::create('__phel_4'),
            ],
        ], $bindings);
    }

    public function test_vector_with_one_symbol_with_rest(): void
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
        $binding = Type::persistentVectorFromArray([
            $bindToA,
            Symbol::create(self::REST_SYMBOL),
            $bindToB,
        ]);

        $bindings = [];
        $this->deconstructor->deconstruct($bindings, $binding, $value);

        self::assertEquals([
            [
                Symbol::create('__phel_1'),
                $value,
            ],
            [
                Symbol::create('__phel_2'),
                Type::persistentListFromArray([
                    Symbol::create(self::FIRST_SYMBOL),
                    Symbol::create('__phel_1'),
                ]),
            ],
            [
                Symbol::create('__phel_3'),
                Type::persistentListFromArray([
                    Symbol::create(self::NEXT_SYMBOL),
                    Symbol::create('__phel_1'),
                ]),
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

    public function test_exception_when_multiple_rest_symbol(): void
    {
        $bindToA = Symbol::create('a');
        $bindToB = Symbol::create('b');
        $value = Symbol::create('x');
        $binding = Type::persistentVectorFromArray([
            $bindToA,
            Symbol::create(self::REST_SYMBOL),
            Symbol::create(self::REST_SYMBOL),
            $bindToB,
        ]);

        $this->expectException(AbstractLocatedException::class);
        $this->expectExceptionMessage('only one symbol can follow the & parameter');

        $bindings = [];
        $this->deconstructor->deconstruct($bindings, $binding, $value);
    }
}
