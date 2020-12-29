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
        $value = 'example value';

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
        $bindings = [];
        $binding = Tuple::create(
            Symbol::create('key-1'),
        );
        $value = 'example value';

        $this->deconstructor->deconstruct($bindings, $binding, $value);

        self::assertEquals([
            [
                Symbol::create('__phel_1'),
                Symbol::create($value),
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
                Symbol::create('key-1'),
                Symbol::create('__phel_2'),
            ],
        ], $bindings);
    }

    public function testTupleWithSymbols(): void
    {
        $bindings = [];
        $binding = Tuple::create(
            Symbol::create('key-1'),
            Symbol::create('key-2'),
        );
        $value = 'example value';

        $this->deconstructor->deconstruct($bindings, $binding, $value);

        self::assertEquals([
            [
                Symbol::create('__phel_1'),
                Symbol::create($value),
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
                Symbol::create('key-1'),
                Symbol::create('__phel_2'),
            ],
            [
                Symbol::create('__phel_4'),
                Tuple::create(
                    Symbol::create('first'),
                    Symbol::create('__phel_3'),
                ),
            ],
            [
                Symbol::create('__phel_5'),
                Tuple::create(
                    Symbol::create('next'),
                    Symbol::create('__phel_3'),
                ),
            ],
            [
                Symbol::create('key-2'),
                Symbol::create('__phel_4'),
            ],
        ], $bindings);
    }

    public function testTupleWithOneSymbolWithRest(): void
    {
        $bindings = [];
        $binding = Tuple::create(
            Symbol::create('key-1'),
            Symbol::create('&'),
            Symbol::create('the-tail'),
        );
        $value = 'example value';

        $this->deconstructor->deconstruct($bindings, $binding, $value);

        self::assertEquals([
            [
                Symbol::create('__phel_1'),
                Symbol::create($value),
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
                Symbol::create('key-1'),
                Symbol::create('__phel_2'),
            ],
            [
                Symbol::create('__phel_4'),
                Symbol::create('__phel_3'),
            ],
            [
                Symbol::create('the-tail'),
                Symbol::create('__phel_4'),
            ],
        ], $bindings);
    }

    public function testExceptionWhenMultipleRestSymbol(): void
    {
        $bindings = [];
        $binding = Tuple::create(
            Symbol::create('key-1'),
            Symbol::create('&'),
            Symbol::create('&'),
            Symbol::create('the-tail'),
        );
        $value = 'example value';

        $this->expectException(PhelCodeException::class);
        $this->expectExceptionMessage('only one symbol can follow the & parameter');
        $this->deconstructor->deconstruct($bindings, $binding, $value);
    }
}
