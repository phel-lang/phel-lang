<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\TupleSymbol\Binding\Deconstructor;

use Phel\Compiler\Analyzer\TupleSymbol\Binding\BindingValidator;
use Phel\Compiler\Analyzer\TupleSymbol\Binding\Deconstructor\TableBindingDeconstructor;
use Phel\Compiler\Analyzer\TupleSymbol\Binding\TupleDeconstructor;
use Phel\Lang\Symbol;
use Phel\Lang\Table;
use Phel\Lang\Tuple;
use PHPUnit\Framework\TestCase;

final class TableBindingDeconstructorTest extends TestCase
{
    protected function setUp(): void
    {
        Symbol::resetGen();
    }

    public function testDeconstruct(): void
    {
        $bindings = [];
        $key = 'test1';
        $binding = Table::fromKVs($key, Tuple::create());
        $value = 'example value';

        $this->createDeconstructor()
            ->deconstruct($bindings, $binding, $value);

        $accessValue = Tuple::create(
            (Symbol::create(Symbol::NAME_PHP_ARRAY_GET))
                ->copyLocationFrom($binding),
            Symbol::create('__phel_1')->copyLocationFrom($binding),
            $key
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

    private function createDeconstructor(): TableBindingDeconstructor
    {
        return new TableBindingDeconstructor(
            new TupleDeconstructor(new BindingValidator())
        );
    }
}
