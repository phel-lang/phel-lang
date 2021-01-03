<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\TupleSymbol\Binding\Deconstructor;

use Phel\Compiler\Analyzer\TypeAnalyzer\TupleSymbol\Binding\Deconstructor\SymbolBindingDeconstructor;
use Phel\Lang\Symbol;
use PHPUnit\Framework\TestCase;

final class SymbolBindingDeconstructorTest extends TestCase
{
    private const EXAMPLE_VALUE = 'example value';

    private SymbolBindingDeconstructor $deconstructor;

    public function setUp(): void
    {
        Symbol::resetGen();

        $this->deconstructor = new SymbolBindingDeconstructor();
    }

    public function testDeconstruct(): void
    {
        $bindings = [];
        $binding = Symbol::create('test');

        $this->deconstructor->deconstruct($bindings, $binding, self::EXAMPLE_VALUE);

        self::assertEquals([
            [$binding, self::EXAMPLE_VALUE],
        ], $bindings);
    }

    public function testWithUnderscoreSymbol(): void
    {
        $bindings = [];
        $binding = Symbol::create('_');

        $this->deconstructor->deconstruct($bindings, $binding, self::EXAMPLE_VALUE);

        self::assertEquals([
            [Symbol::create('__phel_1'), self::EXAMPLE_VALUE],
        ], $bindings);
    }
}
