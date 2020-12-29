<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\TupleSymbol\Binding\Deconstructor;

use Phel\Compiler\Analyzer\TupleSymbol\Binding\Deconstructor\SymbolBindingDeconstructor;
use Phel\Lang\Symbol;
use PHPUnit\Framework\TestCase;

final class SymbolBindingDeconstructorTest extends TestCase
{
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
        $value = 'example value';

        $this->deconstructor->deconstruct($bindings, $binding, $value);

        self::assertEquals([
            [$binding, $value],
        ], $bindings);
    }

    public function testWithUnderscoreSymbol(): void
    {
        $bindings = [];
        $binding = Symbol::create('_');
        $value = 'example value';

        $this->deconstructor->deconstruct($bindings, $binding, $value);

        self::assertEquals([
            [Symbol::create('__phel_1'), $value],
        ], $bindings);
    }
}
