<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer;

use Phel\Compiler\Analyzer\Analyzer;
use Phel\Compiler\Analyzer\TypeAnalyzer\AnalyzeTuple;
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
use Phel\Lang\Symbol;
use Phel\Lang\Tuple;
use Phel\Compiler\Analyzer\Environment\NodeEnvironmentInterface;
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
        self::assertInstanceOf(DefNode::class, $this->tupleAnalyzer->analyze($tuple, NodeEnvironment::empty()));
    }

    public function testSymbolWithNameNs(): void
    {
        $tuple = Tuple::create(Symbol::create(Symbol::NAME_NS), Symbol::create('def-ns'));
        self::assertInstanceOf(NsNode::class, $this->tupleAnalyzer->analyze($tuple, NodeEnvironment::empty()));
    }

    public function testSymbolWithNameFn(): void
    {
        $tuple = Tuple::create(Symbol::create(Symbol::NAME_FN), Tuple::create());
        self::assertInstanceOf(FnNode::class, $this->tupleAnalyzer->analyze($tuple, NodeEnvironment::empty()));
    }

    public function testSymbolWithNameQuote(): void
    {
        $tuple = Tuple::create(Symbol::create(Symbol::NAME_QUOTE), 'any text');
        self::assertInstanceOf(QuoteNode::class, $this->tupleAnalyzer->analyze($tuple, NodeEnvironment::empty()));
    }

    public function testSymbolWithNameDo(): void
    {
        $tuple = Tuple::create(Symbol::create(Symbol::NAME_DO), 1);
        self::assertInstanceOf(DoNode::class, $this->tupleAnalyzer->analyze($tuple, NodeEnvironment::empty()));
    }

    public function testSymbolWithNameIf(): void
    {
        $tuple = Tuple::create(Symbol::create(Symbol::NAME_IF), true, true);
        self::assertInstanceOf(IfNode::class, $this->tupleAnalyzer->analyze($tuple, NodeEnvironment::empty()));
    }

    public function testSymbolWithNameApply(): void
    {
        $tuple = Tuple::create(Symbol::create(Symbol::NAME_APPLY), '+', Tuple::create(''));
        self::assertInstanceOf(ApplyNode::class, $this->tupleAnalyzer->analyze($tuple, NodeEnvironment::empty()));
    }

    public function testSymbolWithNameLet(): void
    {
        $tuple = Tuple::create(Symbol::create(Symbol::NAME_LET), Tuple::create(), Tuple::create());
        self::assertInstanceOf(LetNode::class, $this->tupleAnalyzer->analyze($tuple, NodeEnvironment::empty()));
    }

    public function testSymbolWithNamePhpNew(): void
    {
        $tuple = Tuple::create(Symbol::create(Symbol::NAME_PHP_NEW), '');
        self::assertInstanceOf(PhpNewNode::class, $this->tupleAnalyzer->analyze($tuple, NodeEnvironment::empty()));
    }

    public function testSymbolWithNamePhpObjectCall(): void
    {
        $tuple = Tuple::create(Symbol::create(Symbol::NAME_PHP_OBJECT_CALL), '', Symbol::create(''));
        self::assertInstanceOf(
            PhpObjectCallNode::class,
            $this->tupleAnalyzer->analyze($tuple, NodeEnvironment::empty())
        );
    }

    public function testSymbolWithNamePhpObjectStaticCall(): void
    {
        $tuple = Tuple::create(Symbol::create(Symbol::NAME_PHP_OBJECT_STATIC_CALL), '', Symbol::create(''));
        self::assertInstanceOf(
            PhpObjectCallNode::class,
            $this->tupleAnalyzer->analyze($tuple, NodeEnvironment::empty())
        );
    }

    public function testSymbolWithNamePhpArrayGet(): void
    {
        $tuple = Tuple::create(Symbol::create(Symbol::NAME_PHP_ARRAY_GET));
        self::assertInstanceOf(
            PhpArrayGetNode::class,
            $this->tupleAnalyzer->analyze($tuple, NodeEnvironment::empty())
        );
    }

    public function testSymbolWithNamePhpArraySet(): void
    {
        $tuple = Tuple::create(Symbol::create(Symbol::NAME_PHP_ARRAY_SET));
        self::assertInstanceOf(
            PhpArraySetNode::class,
            $this->tupleAnalyzer->analyze($tuple, NodeEnvironment::empty())
        );
    }

    public function testSymbolWithNamePhpArrayPush(): void
    {
        $tuple = Tuple::create(Symbol::create(Symbol::NAME_PHP_ARRAY_PUSH));
        self::assertInstanceOf(
            PhpArrayPushNode::class,
            $this->tupleAnalyzer->analyze($tuple, NodeEnvironment::empty())
        );
    }

    public function testSymbolWithNamePhpArrayUnset(): void
    {
        $tuple = Tuple::create(Symbol::create(Symbol::NAME_PHP_ARRAY_UNSET));
        self::assertInstanceOf(
            PhpArrayUnsetNode::class,
            $this->tupleAnalyzer->analyze($tuple, NodeEnvironment::empty())
        );
    }

    public function testSymbolWithNameRecur(): void
    {
        $tuple = Tuple::create(Symbol::create(Symbol::NAME_RECUR), 1);
        $recurFrames = [new RecurFrame([Symbol::create(Symbol::NAME_FOREACH)])];
        $nodeEnv = new NodeEnvironment([], NodeEnvironmentInterface::CONTEXT_STATEMENT, [], $recurFrames);
        self::assertInstanceOf(RecurNode::class, $this->tupleAnalyzer->analyze($tuple, $nodeEnv));
    }

    public function testSymbolWithNameTry(): void
    {
        $tuple = Tuple::create(Symbol::create(Symbol::NAME_TRY));
        self::assertInstanceOf(TryNode::class, $this->tupleAnalyzer->analyze($tuple, NodeEnvironment::empty()));
    }

    public function testSymbolWithNameThrow(): void
    {
        $tuple = Tuple::create(Symbol::create(Symbol::NAME_THROW), '');
        self::assertInstanceOf(ThrowNode::class, $this->tupleAnalyzer->analyze($tuple, NodeEnvironment::empty()));
    }

    public function testSymbolWithNameLoop(): void
    {
        $tuple = Tuple::create(Symbol::create(Symbol::NAME_LOOP), Tuple::create(), Tuple::create());
        self::assertInstanceOf(LetNode::class, $this->tupleAnalyzer->analyze($tuple, NodeEnvironment::empty()));
    }

    public function testSymbolWithNameForeach(): void
    {
        $tuple = Tuple::create(
            Symbol::create(Symbol::NAME_FOREACH),
            Tuple::create(Symbol::create(''), Tuple::create()),
            Tuple::create()
        );
        self::assertInstanceOf(ForeachNode::class, $this->tupleAnalyzer->analyze($tuple, NodeEnvironment::empty()));
    }

    public function testSymbolWithNameDefStruct(): void
    {
        $tuple = Tuple::create(Symbol::create(Symbol::NAME_DEF_STRUCT), Symbol::create(''), Tuple::create());
        self::assertInstanceOf(DefStructNode::class, $this->tupleAnalyzer->analyze($tuple, NodeEnvironment::empty()));
    }
}
