<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\TupleSymbol\Binding;

use Phel\Compiler\Analyzer\TypeAnalyzer\TupleSymbol\Binding\BindingValidatorInterface;
use Phel\Compiler\Analyzer\TypeAnalyzer\TupleSymbol\Binding\TupleDeconstructor;
use Phel\Exceptions\AnalyzerException;
use Phel\Lang\Keyword;
use Phel\Lang\PhelArray;
use Phel\Lang\Symbol;
use Phel\Lang\Table;
use Phel\Lang\Tuple;
use PHPUnit\Framework\TestCase;

final class TupleDeconstructorTest extends TestCase
{
    private const EXAMPLE_KEY_1 = 'example key 1';
    private const EXAMPLE_KEY_2 = 'example key 2';
    private const EXAMPLE_KEY_3 = 'example key 3';

    private TupleDeconstructor $deconstructor;

    public function setUp(): void
    {
        Symbol::resetGen();

        $this->deconstructor = new TupleDeconstructor(
            $this->createStub(BindingValidatorInterface::class)
        );
    }

    public function testEmptyTuple(): void
    {
        $bindings = $this->deconstructor->deconstruct(Tuple::create());

        self::assertEquals([], $bindings);
    }

    public function testTupleWithEmptyTuples(): void
    {
        // Test for binding like this (let [[a] [10]
        //                                  [b] [20]])
        // This will be destructured to this:
        // (let [__phel_1 [10]
        //       __phel_2 (first __phel_1)
        //       __phel_3 (next __phel_1)
        //       a __phel_2
        //       __phel_4 [20]
        //       __phel_5 (first __phel_4)
        //       __phel_6 (next __phel_4)
        //       b __phel_5])
        $tuple = Tuple::create(
            Tuple::create(Symbol::create('a')),
            Tuple::create(10),
            Tuple::create(Symbol::create('b')),
            Tuple::create(20)
        );

        $bindings = $this->deconstructor->deconstruct($tuple);

        self::assertEquals([
            [
                Symbol::create('__phel_1'),
                Tuple::create(10),
            ],
            [
                Symbol::create('__phel_2'),
                Tuple::create(
                    Symbol::create('first'),
                    Symbol::create('__phel_1'),
                ),
            ],
            [
                Symbol::create('__phel_3'),
                Tuple::create(
                    Symbol::create('next'),
                    Symbol::create('__phel_1'),
                ),
            ],
            [
                Symbol::create('a'),
                Symbol::create('__phel_2'),
            ],
            [
                Symbol::create('__phel_4'),
                Tuple::create(20),
            ],
            [
                Symbol::create('__phel_5'),
                Tuple::create(
                    Symbol::create('first'),
                    Symbol::create('__phel_4'),
                ),
            ],
            [
                Symbol::create('__phel_6'),
                Tuple::create(
                    Symbol::create('next'),
                    Symbol::create('__phel_4'),
                ),
            ],
            [
                Symbol::create('b'),
                Symbol::create('__phel_5'),
            ],
        ], $bindings);
    }

    public function testTableBinding(): void
    {
        // Test for binding like this (let [@{:key a} x])
        // This will be destructured to this:
        // (let [__phel_1 x
        //       __phel 2 (get __phel_1 :key)
        //       a __phel_2])
        $bindings = $this->deconstructor->deconstruct(
            Tuple::create(Table::fromKVs(new Keyword('key'), Symbol::create('a')), Symbol::create('x'))
        );

        self::assertEquals([
            [
                Symbol::create('__phel_1'),
                Symbol::create('x'),
            ],
            [
                Symbol::create('__phel_2'),
                Tuple::create(
                    Symbol::create(Symbol::NAME_PHP_ARRAY_GET),
                    Symbol::create('__phel_1'),
                    new Keyword('key')
                ),
            ],
            [
                Symbol::create('a'),
                Symbol::create('__phel_2'),
            ],
        ], $bindings);
    }

    public function testArrayBinding(): void
    {
        // Test for binding like this (let [@[0 a] x])
        // This will be destructured to this:
        // (let [__phel_1 x
        //       __phel 2 (get __phel_1 0)
        //       a __phel_2])

        $index = 0;
        $bindTo = Symbol::create('a');
        $binding = PhelArray::create($index, $bindTo); // @[0 a]
        $value = Symbol::create('x');

        $bindings = $this->deconstructor->deconstruct(Tuple::create($binding, $value));

        self::assertEquals([
            // __phel_1 x
            [
                Symbol::create('__phel_1'),
                $value,
            ],
            // __phel_2 (get __phel_1 0)
            [
                Symbol::create('__phel_2'),
                Tuple::create(
                    Symbol::create(Symbol::NAME_PHP_ARRAY_GET),
                    Symbol::create('__phel_1'),
                    $index
                ),
            ],
            // a __phel_2
            [
                $bindTo,
                Symbol::create('__phel_2'),
            ],
        ], $bindings);
    }

    public function testNilBinding(): void
    {
        // Test for binding like this (let [nil x])
        // This will be destructured to this:
        // (let [])
        $bindings = $this->deconstructor->deconstruct(Tuple::create(null, Symbol::create('x')));

        self::assertEquals([], $bindings);
    }

    public function testExceptionWhenNotSupportedBindingTuple(): void
    {
        $this->expectException(AnalyzerException::class);

        $validator = $this->createStub(BindingValidatorInterface::class);
        $validator
            ->method('assertSupportedBinding')
            ->willThrowException(new AnalyzerException(''));

        $deconstructor = new TupleDeconstructor($validator);
        $deconstructor->deconstruct(Tuple::create(Tuple::create()));
    }
}
