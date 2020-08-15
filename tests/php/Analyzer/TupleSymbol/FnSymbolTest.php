<?php

declare(strict_types=1);

namespace PhelTest\Analyzer\TupleSymbol\FnSymbol;

use Phel\Analyzer;
use Phel\Analyzer\TupleSymbol\FnSymbol;
use Phel\Exceptions\PhelCodeException;
use Phel\GlobalEnvironment;
use Phel\Lang\Symbol;
use Phel\Lang\Tuple;
use Phel\NodeEnvironment;
use PHPUnit\Framework\TestCase;

final class FnSymbolTest extends TestCase
{
    private Analyzer $analyzer;

    public function setUp(): void
    {
        $this->analyzer = new Analyzer(new GlobalEnvironment());
    }

    public function testRequiresAtLeastOneArg(): void
    {
        $this->expectException(PhelCodeException::class);
        $this->expectExceptionMessage("'fn requires at least one argument");

        $tuple = Tuple::create();

        (new FnSymbol($this->analyzer))->analyze($tuple, NodeEnvironment::empty());
    }

    public function testSecondArgMustBeATuple(): void
    {
        $this->expectException(PhelCodeException::class);
        $this->expectExceptionMessage("Second argument of 'fn must be a Tuple");

        $tuple = Tuple::create(
            Symbol::create('unknown'),
            Symbol::create('unknown')
        );

        (new FnSymbol($this->analyzer))->analyze($tuple, NodeEnvironment::empty());
    }

    public function testIsVariadic(): void
    {
        $tuple = Tuple::create(
            Symbol::create('unknown'),
            Tuple::create(Symbol::create('unknown'))
        );

        $fnNode = (new FnSymbol($this->analyzer))->analyze($tuple, NodeEnvironment::empty());

        self::assertFalse($fnNode->isVariadic());
    }

    public function testVarNamesMustStartWithLetterOrUnderscore(): void
    {
        $this->expectException(PhelCodeException::class);
        $this->expectExceptionMessageMatches('/(Variable names must start with a letter or underscore)*/i');

        $tuple = Tuple::create(
            Symbol::create('unknown'),
            Tuple::create(  // (fn [param-1])
                Symbol::create(Symbol::NAME_FN),
                Tuple::createBracket(Symbol::create('param-1'))
            ),
        );

        (new FnSymbol($this->analyzer))->analyze($tuple, NodeEnvironment::empty());
    }
}
