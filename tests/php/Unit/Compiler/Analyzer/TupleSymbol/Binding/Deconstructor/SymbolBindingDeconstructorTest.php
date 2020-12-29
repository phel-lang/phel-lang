<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\TupleSymbol\Binding\Deconstructor;

use Phel\Compiler\Analyzer\TupleSymbol\Binding\Deconstructor\SymbolBindingDeconstructor;
use Phel\Lang\Symbol;
use PHPUnit\Framework\TestCase;

final class SymbolBindingDeconstructorTest extends TestCase
{
    protected function setUp(): void
    {
        Symbol::resetGen();
    }

    public function testDeconstructUnderscoreSymbol(): void
    {
        $bindings = [];
        $binding = Symbol::create('_');
        $value = 'example value';

        $this->createDeconstructor()
            ->deconstruct($bindings, $binding, $value);

        self::assertEquals([
            [Symbol::create('__phel_1'), $value],
        ], $bindings);
    }

    public function testDeconstruct(): void
    {
        $bindings = [];
        $binding = Symbol::create('test');
        $value = 'example value';

        $this->createDeconstructor()
            ->deconstruct($bindings, $binding, $value);

        self::assertEquals([
            [$binding, $value],
        ], $bindings);
    }

    private function createDeconstructor(): SymbolBindingDeconstructor
    {
        return new SymbolBindingDeconstructor();
    }
}
