<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\TupleSymbol\Binding\Deconstructor;

use Phel\Compiler\Analyzer\TupleSymbol\Binding\BindingValidator;
use Phel\Compiler\Analyzer\TupleSymbol\Binding\Deconstructor\TupleBindingDeconstructor;
use Phel\Compiler\Analyzer\TupleSymbol\Binding\TupleDeconstructor;
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
            new TupleDeconstructor(new BindingValidator())
        );
    }

    public function testDeconstruct(): void
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
}
