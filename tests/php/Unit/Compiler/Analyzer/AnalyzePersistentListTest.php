<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer;

use Phel\Compiler\Analyzer\Analyzer;
use Phel\Compiler\Analyzer\Ast\ApplyNode;
use Phel\Compiler\Analyzer\Ast\DefNode;
use Phel\Compiler\Analyzer\Ast\DefStructNode;
use Phel\Compiler\Analyzer\Ast\DoNode;
use Phel\Compiler\Analyzer\Ast\FnNode;
use Phel\Compiler\Analyzer\Ast\ForeachNode;
use Phel\Compiler\Analyzer\Ast\IfNode;
use Phel\Compiler\Analyzer\Ast\LetNode;
use Phel\Compiler\Analyzer\Ast\NsNode;
use Phel\Compiler\Analyzer\Ast\PhpArrayGetNode;
use Phel\Compiler\Analyzer\Ast\PhpArrayPushNode;
use Phel\Compiler\Analyzer\Ast\PhpArraySetNode;
use Phel\Compiler\Analyzer\Ast\PhpArrayUnsetNode;
use Phel\Compiler\Analyzer\Ast\PhpNewNode;
use Phel\Compiler\Analyzer\Ast\PhpObjectCallNode;
use Phel\Compiler\Analyzer\Ast\QuoteNode;
use Phel\Compiler\Analyzer\Ast\RecurFrame;
use Phel\Compiler\Analyzer\Ast\RecurNode;
use Phel\Compiler\Analyzer\Ast\ThrowNode;
use Phel\Compiler\Analyzer\Ast\TryNode;
use Phel\Compiler\Analyzer\Environment\GlobalEnvironment;
use Phel\Compiler\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Analyzer\TypeAnalyzer\AnalyzePersistentList;
use Phel\Lang\Symbol;
use Phel\Lang\TypeFactory;
use PHPUnit\Framework\TestCase;

final class AnalyzePersistentListTest extends TestCase
{
    private AnalyzePersistentList $listAnalyzer;

    public function setUp(): void
    {
        $this->listAnalyzer = new AnalyzePersistentList(new Analyzer(new GlobalEnvironment()));
    }

    public function testSymbolWithNameDef(): void
    {
        $list = TypeFactory::getInstance()->persistentListFromArray([
            Symbol::create(Symbol::NAME_DEF),
            Symbol::create('increment'),
            'inc',
        ]);
        self::assertInstanceOf(DefNode::class, $this->listAnalyzer->analyze($list, NodeEnvironment::empty()));
    }

    public function testSymbolWithNameNs(): void
    {
        $list = TypeFactory::getInstance()->persistentListFromArray([
            Symbol::create(Symbol::NAME_NS),
            Symbol::create('def-ns'),
        ]);
        self::assertInstanceOf(NsNode::class, $this->listAnalyzer->analyze($list, NodeEnvironment::empty()));
    }

    public function testSymbolWithNameFn(): void
    {
        $list = TypeFactory::getInstance()->persistentListFromArray([
            Symbol::create(Symbol::NAME_FN),
            TypeFactory::getInstance()->persistentVectorFromArray([]),
        ]);
        self::assertInstanceOf(FnNode::class, $this->listAnalyzer->analyze($list, NodeEnvironment::empty()));
    }

    public function testSymbolWithNameQuote(): void
    {
        $list = TypeFactory::getInstance()->persistentListFromArray([
            Symbol::create(Symbol::NAME_QUOTE),
             'any text',
        ]);
        self::assertInstanceOf(QuoteNode::class, $this->listAnalyzer->analyze($list, NodeEnvironment::empty()));
    }

    public function testSymbolWithNameDo(): void
    {
        $list = TypeFactory::getInstance()->persistentListFromArray([
            Symbol::create(Symbol::NAME_DO), 1,
        ]);
        self::assertInstanceOf(DoNode::class, $this->listAnalyzer->analyze($list, NodeEnvironment::empty()));
    }

    public function testSymbolWithNameIf(): void
    {
        $list = TypeFactory::getInstance()->persistentListFromArray([
            Symbol::create(Symbol::NAME_IF),
            true,
            true,
        ]);
        self::assertInstanceOf(IfNode::class, $this->listAnalyzer->analyze($list, NodeEnvironment::empty()));
    }

    public function testSymbolWithNameApply(): void
    {
        $list = TypeFactory::getInstance()->persistentListFromArray([
            Symbol::create(Symbol::NAME_APPLY), '+', TypeFactory::getInstance()->persistentVectorFromArray(['']),
        ]);
        self::assertInstanceOf(ApplyNode::class, $this->listAnalyzer->analyze($list, NodeEnvironment::empty()));
    }

    public function testSymbolWithNameLet(): void
    {
        $list = TypeFactory::getInstance()->persistentListFromArray([
            Symbol::create(Symbol::NAME_LET),
            TypeFactory::getInstance()->persistentVectorFromArray([]),
            TypeFactory::getInstance()->persistentVectorFromArray([]),
        ]);
        self::assertInstanceOf(LetNode::class, $this->listAnalyzer->analyze($list, NodeEnvironment::empty()));
    }

    public function testSymbolWithNamePhpNew(): void
    {
        $list = TypeFactory::getInstance()->persistentListFromArray([
            Symbol::create(Symbol::NAME_PHP_NEW), '',
        ]);
        self::assertInstanceOf(PhpNewNode::class, $this->listAnalyzer->analyze($list, NodeEnvironment::empty()));
    }

    public function testSymbolWithNamePhpObjectCall(): void
    {
        $list = TypeFactory::getInstance()->persistentListFromArray([
            Symbol::create(Symbol::NAME_PHP_OBJECT_CALL), '', Symbol::create(''),
        ]);
        self::assertInstanceOf(
            PhpObjectCallNode::class,
            $this->listAnalyzer->analyze($list, NodeEnvironment::empty())
        );
    }

    public function testSymbolWithNamePhpObjectStaticCall(): void
    {
        $list = TypeFactory::getInstance()->persistentListFromArray([
            Symbol::create(Symbol::NAME_PHP_OBJECT_STATIC_CALL), '', Symbol::create(''),
        ]);
        self::assertInstanceOf(
            PhpObjectCallNode::class,
            $this->listAnalyzer->analyze($list, NodeEnvironment::empty())
        );
    }

    /*public function testSymbolWithNamePhpArrayGet(): void
    {
        $list = TypeFactory::getInstance()->persistentListFromArray([
            Symbol::create(Symbol::NAME_PHP_ARRAY_GET),
        ]);
        self::assertInstanceOf(
            PhpArrayGetNode::class,
            $this->listAnalyzer->analyze($list, NodeEnvironment::empty())
        );
    }*/

    /*public function testSymbolWithNamePhpArraySet(): void
    {
        $list = TypeFactory::getInstance()->persistentListFromArray([
            Symbol::create(Symbol::NAME_PHP_ARRAY_SET)
        ]);
        self::assertInstanceOf(
            PhpArraySetNode::class,
            $this->listAnalyzer->analyze($list, NodeEnvironment::empty())
        );
    }*/

    /*public function testSymbolWithNamePhpArrayPush(): void
    {
        $list = TypeFactory::getInstance()->persistentListFromArray([
            Symbol::create(Symbol::NAME_PHP_ARRAY_PUSH)
        ]);
        self::assertInstanceOf(
            PhpArrayPushNode::class,
            $this->listAnalyzer->analyze($list, NodeEnvironment::empty())
        );
    }*/

    /*public function testSymbolWithNamePhpArrayUnset(): void
    {
        $list = TypeFactory::getInstance()->persistentListFromArray([
            Symbol::create(Symbol::NAME_PHP_ARRAY_UNSET)
        ]);
        self::assertInstanceOf(
            PhpArrayUnsetNode::class,
            $this->listAnalyzer->analyze($list, NodeEnvironment::empty())
        );
    }*/

    public function testSymbolWithNameRecur(): void
    {
        $list = TypeFactory::getInstance()->persistentListFromArray([
            Symbol::create(Symbol::NAME_RECUR), 1,
        ]);
        $recurFrames = [new RecurFrame([Symbol::create(Symbol::NAME_FOREACH)])];
        $nodeEnv = new NodeEnvironment([], NodeEnvironmentInterface::CONTEXT_STATEMENT, [], $recurFrames);
        self::assertInstanceOf(RecurNode::class, $this->listAnalyzer->analyze($list, $nodeEnv));
    }

    public function testSymbolWithNameTry(): void
    {
        $list = TypeFactory::getInstance()->persistentListFromArray([
            Symbol::create(Symbol::NAME_TRY),
        ]);
        self::assertInstanceOf(TryNode::class, $this->listAnalyzer->analyze($list, NodeEnvironment::empty()));
    }

    public function testSymbolWithNameThrow(): void
    {
        $list = TypeFactory::getInstance()->persistentListFromArray([
            Symbol::create(Symbol::NAME_THROW), '',
        ]);
        self::assertInstanceOf(ThrowNode::class, $this->listAnalyzer->analyze($list, NodeEnvironment::empty()));
    }

    public function testSymbolWithNameLoop(): void
    {
        $list = TypeFactory::getInstance()->persistentListFromArray([
            Symbol::create(Symbol::NAME_LOOP),
            TypeFactory::getInstance()->persistentVectorFromArray([]),
            TypeFactory::getInstance()->persistentVectorFromArray([]),
        ]);
        self::assertInstanceOf(LetNode::class, $this->listAnalyzer->analyze($list, NodeEnvironment::empty()));
    }

    public function testSymbolWithNameForeach(): void
    {
        $list = TypeFactory::getInstance()->persistentListFromArray([
            Symbol::create(Symbol::NAME_FOREACH),
            TypeFactory::getInstance()->persistentVectorFromArray([
                Symbol::create(''), TypeFactory::getInstance()->persistentVectorFromArray([]),
            ]),
            TypeFactory::getInstance()->persistentVectorFromArray([]),
        ]);
        self::assertInstanceOf(ForeachNode::class, $this->listAnalyzer->analyze($list, NodeEnvironment::empty()));
    }

    public function testSymbolWithNameDefStruct(): void
    {
        $list = TypeFactory::getInstance()->persistentListFromArray([
            Symbol::create(Symbol::NAME_DEF_STRUCT),
            Symbol::create(''),
            TypeFactory::getInstance()->persistentVectorFromArray([]),
        ]);
        self::assertInstanceOf(DefStructNode::class, $this->listAnalyzer->analyze($list, NodeEnvironment::empty()));
    }
}
