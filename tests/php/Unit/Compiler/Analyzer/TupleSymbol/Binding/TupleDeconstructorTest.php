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
    public function setUp(): void
    {
        Symbol::resetGen();
    }

    public function testEmptyTuple(): void
    {
        $bindings = $this->createDeconstructor()
            ->deconstruct(Tuple::create());

        self::assertEquals([], $bindings);
    }

    private function createDeconstructor(): TupleDeconstructor
    {
        return new TupleDeconstructor(
            $this->createStub(BindingValidatorInterface::class)
        );
    }

    public function testTupleWithEmptyTuples(): void
    {
        $tuple = Tuple::create(Tuple::create(), 10, Tuple::create(), 20);

        $bindings = $this
            ->createDeconstructor()
            ->deconstruct($tuple);

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
            Symbol::create('key-1'),
            Symbol::create('key-2'),
            Symbol::create('key-3'),
        );

        $bindings = $this
            ->createDeconstructor()
            ->deconstruct($tuple);

        self::assertEquals([
            [
                Symbol::create('key-1'),
                Symbol::create('key-2'),
            ],
            [
                Symbol::create('key-3'),
                null,
            ],
        ], $bindings);
    }

    public function testTupleWithFilledTuples(): void
    {
        $tuple = Tuple::create(
            Tuple::create(
                Symbol::create('one'),
                Symbol::create('20')
            ),
            10
        );

        $bindings = $this
            ->createDeconstructor()
            ->deconstruct($tuple);

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
                Symbol::create('one'),
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
                Symbol::create('20'),
                Symbol::create('__phel_4'),
            ],
        ], $bindings);
    }

    public function testExceptionWhenNotSupportedBindingTuple(): void
    {
        $this->expectException(AnalyzerException::class);

        $this->createDeconstructorWithException()
            ->deconstruct(Tuple::create(Tuple::create()));
    }

    private function createDeconstructorWithException(): TupleDeconstructor
    {
        $validator = $this->createStub(BindingValidatorInterface::class);
        $validator
            ->method('assertSupportedBinding')
            ->willThrowException(new AnalyzerException(''));

        return new TupleDeconstructor($validator);
    }
}
