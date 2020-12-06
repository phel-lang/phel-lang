<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\TupleSymbol;

use Phel\Compiler\Analyzer;
use Phel\Compiler\Analyzer\TupleSymbol\DefStructSymbol;
use Phel\Compiler\AnalyzerInterface;
use Phel\Compiler\NodeEnvironment;
use Phel\Exceptions\PhelCodeException;
use Phel\Compiler\GlobalEnvironment;
use Phel\Lang\Symbol;
use Phel\Lang\Tuple;
use PHPUnit\Framework\TestCase;

final class DefStructSymbolTest extends TestCase
{
    private AnalyzerInterface $analyzer;

    public function setUp(): void
    {
        $this->analyzer = new Analyzer(new GlobalEnvironment());
    }

    public function testWithWrongNumberOfArguments(): void
    {
        $this->expectException(PhelCodeException::class);
        $this->expectExceptionMessage("Exactly two arguments are required for 'defstruct. Got 1");

        $tuple = Tuple::create(
            Symbol::create(Symbol::NAME_DEF_STRUCT),
        );

        (new DefStructSymbol($this->analyzer))
            ->analyze($tuple, NodeEnvironment::empty());
    }

    public function testFirstArgIsNotSymbol(): void
    {
        $this->expectException(PhelCodeException::class);
        $this->expectExceptionMessage("First argument of 'defstruct must be a Symbol.");

        $tuple = Tuple::create(
            Symbol::create(Symbol::NAME_DEF_STRUCT),
            '',
            Tuple::create()
        );

        (new DefStructSymbol($this->analyzer))
            ->analyze($tuple, NodeEnvironment::empty());
    }

    public function testSecondArgIsNotTuple(): void
    {
        $this->expectException(PhelCodeException::class);
        $this->expectExceptionMessage("Second argument of 'defstruct must be a Tuple.");

        $tuple = Tuple::create(
            Symbol::create(Symbol::NAME_DEF_STRUCT),
            Symbol::create('request'),
            ''
        );

        (new DefStructSymbol($this->analyzer))
            ->analyze($tuple, NodeEnvironment::empty());
    }

    public function testTupleElemsAreNotSymbols(): void
    {
        $this->expectException(PhelCodeException::class);
        $this->expectExceptionMessage('Defstruct field elements must be Symbols.');

        $tuple = Tuple::create(
            Symbol::create(Symbol::NAME_DEF_STRUCT),
            Symbol::create('request'),
            Tuple::create('method')
        );

        (new DefStructSymbol($this->analyzer))
            ->analyze($tuple, NodeEnvironment::empty());
    }

    public function testDefStructSymbol(): void
    {
        $tuple = Tuple::create(
            Symbol::create(Symbol::NAME_DEF_STRUCT),
            Symbol::create('request'),
            Tuple::create(Symbol::create('method'), Symbol::create('uri'))
        );

        $defStructNode = (new DefStructSymbol($this->analyzer))
            ->analyze($tuple, NodeEnvironment::empty());

        self::assertSame('request', $defStructNode->getName()->getName());
        self::assertSame('user', $defStructNode->getNamespace());
        $symbols = $defStructNode->getParams();
        self::assertCount(2, $symbols);
        self::assertSame('method', $symbols[0]->getName());
        self::assertSame('uri', $symbols[1]->getName());
        $paramsAsKeywords = $defStructNode->getParamsAsKeywords();
        self::assertCount(2, $paramsAsKeywords);
        self::assertSame('method', $paramsAsKeywords[0]->getName());
        self::assertSame('uri', $paramsAsKeywords[1]->getName());
    }
}
