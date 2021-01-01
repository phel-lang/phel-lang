<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\TupleSymbol;

use Phel\Compiler\Analyzer;
use Phel\Compiler\Analyzer\TupleSymbol\Binding\BindingValidator;
use Phel\Compiler\Analyzer\TupleSymbol\LoopSymbolInterface;
use Phel\Compiler\GlobalEnvironment;
use Phel\Compiler\NodeEnvironment;
use Phel\Exceptions\PhelCodeException;
use Phel\Lang\Symbol;
use Phel\Lang\Tuple;
use PHPUnit\Framework\TestCase;

final class LoopSymbolTest extends TestCase
{
    public function testWrongSymbolName(): void
    {
        $this->expectException(PhelCodeException::class);
        $this->expectExceptionMessage("This is not a 'loop.");

        $tuple = Tuple::create(Symbol::create('unknown'));
        $env = NodeEnvironment::empty();

        $this->createLoopSymbol()->analyze($tuple, $env);
    }

    private function createLoopSymbol(): LoopSymbolInterface
    {
        return new LoopSymbolInterface(
            new Analyzer(new GlobalEnvironment()),
            new BindingValidator()
        );
    }
}
