<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\TupleSymbol\Binding\Deconstructor;

use Phel\Compiler\Analyzer\TupleSymbol\Binding\BindingValidatorInterface;
use Phel\Compiler\Analyzer\TupleSymbol\Binding\Deconstructor\TableBindingDeconstructor;
use Phel\Compiler\Analyzer\TupleSymbol\Binding\TupleDeconstructor;
use Phel\Lang\Symbol;
use Phel\Lang\Table;
use Phel\Lang\Tuple;
use PHPUnit\Framework\TestCase;

final class TableBindingDeconstructorTest extends TestCase
{
    private TableBindingDeconstructor $deconstructor;

    public function setUp(): void
    {
        Symbol::resetGen();

        $this->deconstructor = new TableBindingDeconstructor(
            new TupleDeconstructor(
                $this->createMock(BindingValidatorInterface::class)
            )
        );
    }

    public function testDeconstruct(): void
    {
        $bindings = [];
        $binding = Table::fromKVs();
        $value = 'example value';

        $this->deconstructor->deconstruct($bindings, $binding, $value);

        self::assertEquals([
            [
                Symbol::create('__phel_1'),
                $value,
            ],
        ], $bindings);
    }

    public function testDeconstructTableWithTuple(): void
    {
        $bindings = [];
        $key = 'test1';
        $binding = Table::fromKVs($key, Tuple::create());
        $value = 'example value';

        $this->deconstructor->deconstruct($bindings, $binding, $value);

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
}
