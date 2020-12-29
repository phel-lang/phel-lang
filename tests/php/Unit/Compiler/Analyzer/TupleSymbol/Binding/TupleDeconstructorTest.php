<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\TupleSymbol\Binding;

use Phel\Compiler\Analyzer\TupleSymbol\Binding\BindingValidator;
use Phel\Compiler\Analyzer\TupleSymbol\Binding\TupleDeconstructor;
use Phel\Lang\Symbol;
use Phel\Lang\Tuple;
use PHPUnit\Framework\TestCase;

final class TupleDeconstructorTest extends TestCase
{
    private TupleDeconstructor $deconstructor;

    public function setUp(): void
    {
        Symbol::resetGen();

        $this->deconstructor = new TupleDeconstructor(
            new BindingValidator()
        );
    }

    public function testEmptyTuple(): void
    {
        $bindings = $this->deconstructor->deconstruct(Tuple::create());

        self::assertEquals([], $bindings);
    }

    public function testTuple(): void
    {
        $bindings = $this->deconstructor->deconstruct(
            Tuple::create(
                Tuple::create()
            )
        );

        self::assertEquals([
            [
                Symbol::create('__phel_1'),
                null,
            ],
        ], $bindings);
    }
}
