<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\TupleSymbol;

use Phel\Compiler\Analyzer\Analyzer;
use Phel\Compiler\Analyzer\TypeAnalyzer\TupleSymbol\Binding\TupleDeconstructorInterface;
use Phel\Compiler\Analyzer\TypeAnalyzer\TupleSymbol\LetSymbol;
use Phel\Compiler\Analyzer\Environment\GlobalEnvironment;
use Phel\Compiler\Analyzer\Environment\NodeEnvironment;
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

    private function createLetSymbol(): LetSymbol
    {
        return new LetSymbol(
            new Analyzer(new GlobalEnvironment()),
            $this->createMock(TupleDeconstructorInterface::class)
        );
    }
}
