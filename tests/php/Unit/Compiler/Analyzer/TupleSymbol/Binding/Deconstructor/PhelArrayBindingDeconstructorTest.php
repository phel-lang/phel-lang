<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\TupleSymbol\Binding\Deconstructor;

use Phel\Compiler\Analyzer\TupleSymbol\Binding\BindingValidator;
use Phel\Compiler\Analyzer\TupleSymbol\Binding\Deconstructor\PhelArrayBindingDeconstructor;
use Phel\Compiler\Analyzer\TupleSymbol\Binding\TupleDeconstructor;
use Phel\Lang\PhelArray;
use Phel\Lang\Symbol;
use Phel\Lang\Tuple;
use PHPUnit\Framework\TestCase;

final class PhelArrayBindingDeconstructorTest extends TestCase
{
    private PhelArrayBindingDeconstructor $deconstructor;

    public function setUp(): void
    {
        Symbol::resetGen();

        $this->deconstructor = new PhelArrayBindingDeconstructor(
            new TupleDeconstructor(new BindingValidator())
        );
    }

    public function testDeconstruct(): void
    {
        $bindings = [];
        $index = 'test1';
        $binding = PhelArray::create(
            $index,
            Tuple::create(),
        );
        $value = 'example value';

        $this->deconstructor->deconstruct($bindings, $binding, $value);

        $accessValue = Tuple::create(
            (Symbol::create(Symbol::NAME_PHP_ARRAY_GET))
                ->copyLocationFrom($binding),
            Symbol::create('__phel_1')->copyLocationFrom($binding),
            $index
        )->copyLocationFrom($binding);

        self::assertEquals([
            [
                Symbol::create('__phel_1'),
                $value,
            ],
            [
                Symbol::create('__phel_2'),
                $accessValue,
            ],
            [
                Symbol::create('__phel_3'),
                Symbol::create('__phel_2'),
            ],
        ], $bindings);
    }
}
