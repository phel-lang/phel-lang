<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\TupleSymbol\Binding;

use Phel\Compiler\Analyzer\TupleSymbol\Binding\BindingValidatorInterface;
use Phel\Compiler\Analyzer\TupleSymbol\Binding\TupleDeconstructor;
use Phel\Exceptions\AnalyzerException;
use Phel\Lang\Symbol;
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
        $tuple = Tuple::create(Tuple::create(), 10, Tuple::create(), 20);

        $bindings = $this->deconstructor->deconstruct($tuple);

        self::assertEquals([
            [
                Symbol::create('__phel_1'),
                10,
            ],
            [
                Symbol::create('__phel_2'),
                20,
            ],
        ], $bindings);
    }

    public function testTupleWithSymbols(): void
    {
        $tuple = Tuple::create(
            Symbol::create(self::EXAMPLE_KEY_1),
            Symbol::create(self::EXAMPLE_KEY_2),
            Symbol::create(self::EXAMPLE_KEY_3),
        );

        $bindings = $this->deconstructor->deconstruct($tuple);

        self::assertEquals([
            [
                Symbol::create(self::EXAMPLE_KEY_1),
                Symbol::create(self::EXAMPLE_KEY_2),
            ],
            [
                Symbol::create(self::EXAMPLE_KEY_3),
                null,
            ],
        ], $bindings);
    }

    public function testTupleWithFilledTuples(): void
    {
        $tuple = Tuple::create(
            Tuple::create(
                Symbol::create(self::EXAMPLE_KEY_1),
                Symbol::create(self::EXAMPLE_KEY_2)
            ),
            10
        );

        $bindings = $this->deconstructor->deconstruct($tuple);

        self::assertEquals([
            [
                Symbol::create('__phel_1'),
                10,
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
                Symbol::create(self::EXAMPLE_KEY_1),
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
                Symbol::create(self::EXAMPLE_KEY_2),
                Symbol::create('__phel_4'),
            ],
        ], $bindings);
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
