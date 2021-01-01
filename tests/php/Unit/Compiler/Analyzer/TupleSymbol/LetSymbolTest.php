<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\TupleSymbol;

use Phel\Compiler\Analyzer;
use Phel\Compiler\Analyzer\TupleSymbol\Binding\TupleDeconstructorInterface;
use Phel\Compiler\Analyzer\TupleSymbol\LetSymbolInterface;
use Phel\Compiler\GlobalEnvironment;
use Phel\Compiler\NodeEnvironment;
use Phel\Exceptions\PhelCodeException;
use Phel\Lang\Symbol;
use Phel\Lang\Tuple;
use PHPUnit\Framework\TestCase;

final class LetSymbolTest extends TestCase
{
    public function testWrongSymbolName(): void
    {
        $this->expectException(PhelCodeException::class);
        $this->expectExceptionMessage("This is not a 'let.");

        $tuple = Tuple::create(Symbol::create('unknown'));
        $env = NodeEnvironment::empty();

        $this->createLetSymbol()->analyze($tuple, $env);
    }

    private function createLetSymbol(): LetSymbolInterface
    {
        return new LetSymbolInterface(
            new Analyzer(new GlobalEnvironment()),
            $this->createMock(TupleDeconstructorInterface::class)
        );
    }
}
