<?php

declare(strict_types=1);

namespace PhelTest\Unit\Transpiler\Analyzer\SpecialForm\Binding\Deconstructor;

use Phel\Lang\Symbol;
use Phel\Transpiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\Binding\Deconstructor\SymbolBindingDeconstructor;
use PHPUnit\Framework\TestCase;

final class SymbolBindingDeconstructorTest extends TestCase
{
    private const EXAMPLE_VALUE = 'example value';

    private SymbolBindingDeconstructor $deconstructor;

    protected function setUp(): void
    {
        Symbol::resetGen();

        $this->deconstructor = new SymbolBindingDeconstructor();
    }

    public function test_deconstruct(): void
    {
        $bindings = [];
        $binding = Symbol::create('test');

        $this->deconstructor->deconstruct($bindings, $binding, self::EXAMPLE_VALUE);

        self::assertEquals([
            [$binding, self::EXAMPLE_VALUE],
        ], $bindings);
    }

    public function test_with_underscore_symbol(): void
    {
        $bindings = [];
        $binding = Symbol::create('_');

        $this->deconstructor->deconstruct($bindings, $binding, self::EXAMPLE_VALUE);

        self::assertEquals([
            [Symbol::create('__phel_1'), self::EXAMPLE_VALUE],
        ], $bindings);
    }
}
