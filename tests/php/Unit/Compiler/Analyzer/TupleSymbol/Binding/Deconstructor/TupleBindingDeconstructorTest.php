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
    protected function setUp(): void
    {
        Symbol::resetGen();
    }

    public function testDeconstruct(): void
    {
        $bindings = [];
        $binding = Tuple::create();
        $value = 'example value';

        $this->createDeconstructor()
            ->deconstruct($bindings, $binding, $value);

        self::assertEquals([
            [
                Symbol::create('__phel_1'),
                $value,
            ],
        ], $bindings);
    }

    private function createDeconstructor(): TupleBindingDeconstructor
    {
        return new TupleBindingDeconstructor(
            new TupleDeconstructor(new BindingValidator())
        );
    }
}
