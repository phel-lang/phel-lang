<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\SpecialForm;

use Phel\Compiler\Analyzer\Analyzer;
use Phel\Compiler\Analyzer\AnalyzerInterface;
use Phel\Compiler\Analyzer\Ast\DefStructNode;
use Phel\Compiler\Analyzer\Environment\GlobalEnvironment;
use Phel\Compiler\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Analyzer\TypeAnalyzer\SpecialForm\DefStructSymbol;
use Phel\Compiler\Exceptions\AbstractLocatedException;
use Phel\Lang\Symbol;
use Phel\Lang\TypeFactory;
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
        $this->expectException(AbstractLocatedException::class);
        $this->expectExceptionMessage("Exactly two arguments are required for 'defstruct. Got 1");

        $list = TypeFactory::getInstance()->persistentListFromArray([
            Symbol::create(Symbol::NAME_DEF_STRUCT),
        ]);

        (new DefStructSymbol($this->analyzer))
            ->analyze($list, NodeEnvironment::empty());
    }

    public function testFirstArgIsNotSymbol(): void
    {
        $this->expectException(AbstractLocatedException::class);
        $this->expectExceptionMessage("First argument of 'defstruct must be a Symbol.");

        $list = TypeFactory::getInstance()->persistentListFromArray([
            Symbol::create(Symbol::NAME_DEF_STRUCT),
            '',
            TypeFactory::getInstance()->persistentVectorFromArray([]),
        ]);

        (new DefStructSymbol($this->analyzer))
            ->analyze($list, NodeEnvironment::empty());
    }

    public function testSecondArgIsNotVector(): void
    {
        $this->expectException(AbstractLocatedException::class);
        $this->expectExceptionMessage("Second argument of 'defstruct must be a vector.");

        $list = TypeFactory::getInstance()->persistentListFromArray([
            Symbol::create(Symbol::NAME_DEF_STRUCT),
            Symbol::create('request'),
            '',
        ]);

        (new DefStructSymbol($this->analyzer))
            ->analyze($list, NodeEnvironment::empty());
    }

    public function testVectorElemsAreNotSymbols(): void
    {
        $this->expectException(AbstractLocatedException::class);
        $this->expectExceptionMessage('Defstruct field elements must be Symbols.');

        $list = TypeFactory::getInstance()->persistentListFromArray([
            Symbol::create(Symbol::NAME_DEF_STRUCT),
            Symbol::create('request'),
            TypeFactory::getInstance()->persistentVectorFromArray(['method']),
        ]);

        (new DefStructSymbol($this->analyzer))
            ->analyze($list, NodeEnvironment::empty());
    }

    public function testDefStructSymbol(): void
    {
        $list = TypeFactory::getInstance()->persistentListFromArray([
            Symbol::create(Symbol::NAME_DEF_STRUCT),
            Symbol::create('request'),
            TypeFactory::getInstance()->persistentVectorFromArray([Symbol::create('method'), Symbol::create('uri')]),
        ]);

        $defStructNode = (new DefStructSymbol($this->analyzer))
            ->analyze($list, NodeEnvironment::empty());

        self::assertEquals(
            new DefStructNode(
                NodeEnvironment::empty(),
                'user',
                Symbol::create('request'),
                [
                    Symbol::create('method'),
                    Symbol::create('uri'),
                ]
            ),
            $defStructNode
        );
    }
}
