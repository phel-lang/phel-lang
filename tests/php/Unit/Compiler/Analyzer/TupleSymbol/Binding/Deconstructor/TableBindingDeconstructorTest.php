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
    private const EXAMPLE_KEY = 'example key';
    private const EXAMPLE_VALUE = 'example value';

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

        $this->deconstructor->deconstruct($bindings, $binding, self::EXAMPLE_VALUE);

        self::assertEquals([
            [
                Symbol::create('__phel_1'),
                self::EXAMPLE_VALUE,
            ],
        ], $bindings);
    }

    public function testDeconstructTableWithTuple(): void
    {
        $bindings = [];
        $binding = Table::fromKVs(self::EXAMPLE_KEY, Tuple::create());

        $this->deconstructor->deconstruct($bindings, $binding, self::EXAMPLE_VALUE);

        self::assertEquals([
            [
                Symbol::create('__phel_1'),
                self::EXAMPLE_VALUE,
            ],
            [
                Symbol::create('__phel_2'),
                Tuple::create(
                    Symbol::create(Symbol::NAME_PHP_ARRAY_GET),
                    Symbol::create('__phel_1'),
                    self::EXAMPLE_KEY
                ),
            ],
            [
                Symbol::create('__phel_3'),
                Symbol::create('__phel_2'),
            ],
        ], $bindings);
    }
}
