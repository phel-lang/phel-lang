<?php

declare(strict_types=1);

namespace Phel\Analyzer;

use Phel\Analyzer;
use Phel\Ast\ApplyNode;
use Phel\Ast\DefNode;
use Phel\Ast\DefStructNode;
use Phel\Ast\DoNode;
use Phel\Ast\FnNode;
use Phel\Ast\ForeachNode;
use Phel\Ast\IfNode;
use Phel\Ast\LetNode;
use Phel\Ast\NsNode;
use Phel\Ast\PhpArrayGetNode;
use Phel\Ast\PhpArrayPushNode;
use Phel\Ast\PhpArraySetNode;
use Phel\Ast\PhpArrayUnsetNode;
use Phel\Ast\PhpNewNode;
use Phel\Ast\PhpObjectCallNode;
use Phel\Ast\QuoteNode;
use Phel\Ast\RecurNode;
use Phel\Ast\ThrowNode;
use Phel\Ast\TryNode;
use Phel\GlobalEnvironment;
use Phel\Lang\Symbol;
use Phel\Lang\Tuple;
use Phel\NodeEnvironment;
use Phel\RecurFrame;
use PHPUnit\Framework\TestCase;

final class AnalyzeTupleTest extends TestCase
{
    private AnalyzeTuple $tupleAnalyzer;

    public function setUp(): void
    {
        $this->tupleAnalyzer = new AnalyzeTuple(new Analyzer(new GlobalEnvironment()));
    }

    public function testSymbolWithNameDef(): void
    {
        $tuple = Tuple::create(Symbol::create(Symbol::NAME_DEF), Symbol::create('increment'), 'inc');
        self::assertInstanceOf(DefNode::class, $this->tupleAnalyzer->__invoke($tuple, NodeEnvironment::empty()));
    }

    public function testSymbolWithNameNs(): void
    {
        $tuple = Tuple::create(Symbol::create(Symbol::NAME_NS), Symbol::create('def-ns'));
        self::assertInstanceOf(NsNode::class, $this->tupleAnalyzer->__invoke($tuple, NodeEnvironment::empty()));
    }

    public function testSymbolWithNameFn(): void
    {
        $tuple = Tuple::create(Symbol::create(Symbol::NAME_FN), Tuple::create());
        self::assertInstanceOf(FnNode::class, $this->tupleAnalyzer->__invoke($tuple, NodeEnvironment::empty()));
    }

    public function testSymbolWithNameQuote(): void
    {
        $tuple = Tuple::create(Symbol::create(Symbol::NAME_QUOTE), 'any text');
        self::assertInstanceOf(QuoteNode::class, $this->tupleAnalyzer->__invoke($tuple, NodeEnvironment::empty()));
    }

    public function testSymbolWithNameDo(): void
    {
        $tuple = Tuple::create(Symbol::create(Symbol::NAME_DO), 1);
        self::assertInstanceOf(DoNode::class, $this->tupleAnalyzer->__invoke($tuple, NodeEnvironment::empty()));
    }

    public function testSymbolWithNameIf(): void
    {
        $tuple = Tuple::create(Symbol::create(Symbol::NAME_IF), true, true);
        self::assertInstanceOf(IfNode::class, $this->tupleAnalyzer->__invoke($tuple, NodeEnvironment::empty()));
    }

    public function testSymbolWithNameApply(): void
    {
        $tuple = Tuple::create(Symbol::create(Symbol::NAME_APPLY), '+', Tuple::create(''));
        self::assertInstanceOf(ApplyNode::class, $this->tupleAnalyzer->__invoke($tuple, NodeEnvironment::empty()));
    }

    public function testSymbolWithNameLet(): void
    {
        $tuple = Tuple::create(Symbol::create(Symbol::NAME_LET), Tuple::create(), Tuple::create());
        self::assertInstanceOf(LetNode::class, $this->tupleAnalyzer->__invoke($tuple, NodeEnvironment::empty()));
    }

    public function testSymbolWithNamePhpNew(): void
    {
        $tuple = Tuple::create(Symbol::create(Symbol::NAME_PHP_NEW), '');
        self::assertInstanceOf(PhpNewNode::class, $this->tupleAnalyzer->__invoke($tuple, NodeEnvironment::empty()));
    }

    public function testSymbolWithNamePhpObjectCall(): void
    {
        $tuple = Tuple::create(Symbol::create(Symbol::NAME_PHP_OBJECT_CALL), '', Symbol::create(''));
        self::assertInstanceOf(
            PhpObjectCallNode::class,
            $this->tupleAnalyzer->__invoke($tuple, NodeEnvironment::empty())
        );
    }

    public function testSymbolWithNamePhpObjectStaticCall(): void
    {
        $tuple = Tuple::create(Symbol::create(Symbol::NAME_PHP_OBJECT_STATIC_CALL), '', Symbol::create(''));
        self::assertInstanceOf(
            PhpObjectCallNode::class,
            $this->tupleAnalyzer->__invoke($tuple, NodeEnvironment::empty())
        );
    }

    public function testSymbolWithNamePhpArrayGet(): void
    {
        $tuple = Tuple::create(Symbol::create(Symbol::NAME_PHP_ARRAY_GET));
        self::assertInstanceOf(
            PhpArrayGetNode::class,
            $this->tupleAnalyzer->__invoke($tuple, NodeEnvironment::empty())
        );
    }

    public function testSymbolWithNamePhpArraySet(): void
    {
        $tuple = Tuple::create(Symbol::create(Symbol::NAME_PHP_ARRAY_SET));
        self::assertInstanceOf(
            PhpArraySetNode::class,
            $this->tupleAnalyzer->__invoke($tuple, NodeEnvironment::empty())
        );
    }

    public function testSymbolWithNamePhpArrayPush(): void
    {
        $tuple = Tuple::create(Symbol::create(Symbol::NAME_PHP_ARRAY_PUSH));
        self::assertInstanceOf(
            PhpArrayPushNode::class,
            $this->tupleAnalyzer->__invoke($tuple, NodeEnvironment::empty())
        );
    }

    public function testSymbolWithNamePhpArrayUnset(): void
    {
        $tuple = Tuple::create(Symbol::create(Symbol::NAME_PHP_ARRAY_UNSET));
        self::assertInstanceOf(
            PhpArrayUnsetNode::class,
            $this->tupleAnalyzer->__invoke($tuple, NodeEnvironment::empty())
        );
    }

    public function testSymbolWithNameRecur(): void
    {
        $tuple = Tuple::create(Symbol::create(Symbol::NAME_RECUR), 1);
        $recurFrames = [new RecurFrame([Symbol::create(Symbol::NAME_FOREACH)])];
        $nodeEnv = new NodeEnvironment([], NodeEnvironment::CTX_STMT, [], $recurFrames);
        self::assertInstanceOf(RecurNode::class, $this->tupleAnalyzer->__invoke($tuple, $nodeEnv));
    }

    public function testSymbolWithNameTry(): void
    {
        $tuple = Tuple::create(Symbol::create(Symbol::NAME_TRY));
        self::assertInstanceOf(TryNode::class, $this->tupleAnalyzer->__invoke($tuple, NodeEnvironment::empty()));
    }

    public function testSymbolWithNameThrow(): void
    {
        $tuple = Tuple::create(Symbol::create(Symbol::NAME_THROW), '');
        self::assertInstanceOf(ThrowNode::class, $this->tupleAnalyzer->__invoke($tuple, NodeEnvironment::empty()));
    }

    public function testSymbolWithNameLoop(): void
    {
        $tuple = Tuple::create(Symbol::create(Symbol::NAME_LOOP), Tuple::create(), Tuple::create());
        self::assertInstanceOf(LetNode::class, $this->tupleAnalyzer->__invoke($tuple, NodeEnvironment::empty()));
    }

    public function testSymbolWithNameForeach(): void
    {
        $tuple = Tuple::create(
            Symbol::create(Symbol::NAME_FOREACH),
            Tuple::create(Symbol::create(''), Tuple::create()),
            Tuple::create()
        );
        self::assertInstanceOf(ForeachNode::class, $this->tupleAnalyzer->__invoke($tuple, NodeEnvironment::empty()));
    }

    public function testSymbolWithNameDefStruct(): void
    {
        $tuple = Tuple::create(Symbol::create(Symbol::NAME_DEF_STRUCT), Symbol::create(''), Tuple::create());
        self::assertInstanceOf(DefStructNode::class, $this->tupleAnalyzer->__invoke($tuple, NodeEnvironment::empty()));
    }
}
