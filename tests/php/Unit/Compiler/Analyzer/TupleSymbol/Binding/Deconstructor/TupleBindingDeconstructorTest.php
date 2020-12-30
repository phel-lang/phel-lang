<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\TupleSymbol\Binding\Deconstructor;

use Phel\Compiler\Analyzer\TupleSymbol\Binding\BindingValidatorInterface;
use Phel\Compiler\Analyzer\TupleSymbol\Binding\Deconstructor\TupleBindingDeconstructor;
use Phel\Compiler\Analyzer\TupleSymbol\Binding\TupleDeconstructor;
use Phel\Exceptions\PhelCodeException;
use Phel\Lang\Symbol;
use Phel\Lang\Tuple;
use PHPUnit\Framework\TestCase;

final class TupleBindingDeconstructorTest extends TestCase
{
    private const EXAMPLE_VALUE = 'example value';
    private const EXAMPLE_KEY_1 = 'example key 1';
    private const EXAMPLE_KEY_2 = 'example key 2';
    private const EXAMPLE_TAIL = 'example tail';

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
        $bindings = [];
        $binding = Tuple::create();

        $this->deconstructor->deconstruct($bindings, $binding, self::EXAMPLE_VALUE);

        self::assertEquals([
            [
                Symbol::create('__phel_1'),
                self::EXAMPLE_VALUE,
            ],
        ], $bindings);
    }

    public function testTupleWithOneSymbol(): void
    {
        $bindings = [];

        $binding = Tuple::create(
            Symbol::create(self::EXAMPLE_KEY_1),
        );

        $this->deconstructor->deconstruct($bindings, $binding, self::EXAMPLE_VALUE);

        self::assertEquals([
            [
                Symbol::create('__phel_1'),
                Symbol::create(self::EXAMPLE_VALUE),
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
                Symbol::create(self::EXAMPLE_KEY_1),
                Symbol::create('__phel_2'),
            ],
        ], $bindings);
    }

    public function testTupleWithSymbols(): void
    {
        $bindings = [];

        $binding = Tuple::create(
            Symbol::create(self::EXAMPLE_KEY_1),
            Symbol::create(self::EXAMPLE_KEY_2),
        );

        $this->deconstructor->deconstruct($bindings, $binding, self::EXAMPLE_VALUE);

        self::assertEquals([
            [
                Symbol::create('__phel_1'),
                Symbol::create(self::EXAMPLE_VALUE),
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
                Symbol::create(self::EXAMPLE_KEY_1),
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
                Symbol::create(self::EXAMPLE_KEY_2),
                Symbol::create('__phel_4'),
            ],
        ], $bindings);
    }

    public function testTupleWithOneSymbolWithRest(): void
    {
        $bindings = [];
        $binding = Tuple::create(
            Symbol::create(self::EXAMPLE_KEY_1),
            Symbol::create(self::REST_SYMBOL),
            Symbol::create(self::EXAMPLE_TAIL),
        );

        $this->deconstructor->deconstruct($bindings, $binding, self::EXAMPLE_VALUE);

        self::assertEquals([
            [
                Symbol::create('__phel_1'),
                Symbol::create(self::EXAMPLE_VALUE),
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
                Symbol::create(self::EXAMPLE_KEY_1),
                Symbol::create('__phel_2'),
            ],
            [
                Symbol::create('__phel_4'),
                Symbol::create('__phel_3'),
            ],
            [
                Symbol::create(self::EXAMPLE_TAIL),
                Symbol::create('__phel_4'),
            ],
        ], $bindings);
    }

    public function testExceptionWhenMultipleRestSymbol(): void
    {
        $bindings = [];

        $binding = Tuple::create(
            Symbol::create(self::EXAMPLE_KEY_1),
            Symbol::create(self::REST_SYMBOL),
            Symbol::create(self::REST_SYMBOL),
            Symbol::create(self::EXAMPLE_TAIL),
        );

        $this->expectException(PhelCodeException::class);
        $this->expectExceptionMessage('only one symbol can follow the & parameter');
        $this->deconstructor->deconstruct($bindings, $binding, self::EXAMPLE_VALUE);
    }
}
