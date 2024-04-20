<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer;

use Phel\Lang\Symbol;
use Phel\Lang\TypeFactory;
use Phel\Transpiler\Domain\Analyzer\Analyzer;
use Phel\Transpiler\Domain\Analyzer\Ast\ApplyNode;
use Phel\Transpiler\Domain\Analyzer\Ast\DefNode;
use Phel\Transpiler\Domain\Analyzer\Ast\DefStructNode;
use Phel\Transpiler\Domain\Analyzer\Ast\DoNode;
use Phel\Transpiler\Domain\Analyzer\Ast\FnNode;
use Phel\Transpiler\Domain\Analyzer\Ast\ForeachNode;
use Phel\Transpiler\Domain\Analyzer\Ast\IfNode;
use Phel\Transpiler\Domain\Analyzer\Ast\LetNode;
use Phel\Transpiler\Domain\Analyzer\Ast\NsNode;
use Phel\Transpiler\Domain\Analyzer\Ast\PhpArrayGetNode;
use Phel\Transpiler\Domain\Analyzer\Ast\PhpArrayPushNode;
use Phel\Transpiler\Domain\Analyzer\Ast\PhpArraySetNode;
use Phel\Transpiler\Domain\Analyzer\Ast\PhpArrayUnsetNode;
use Phel\Transpiler\Domain\Analyzer\Ast\PhpNewNode;
use Phel\Transpiler\Domain\Analyzer\Ast\PhpObjectCallNode;
use Phel\Transpiler\Domain\Analyzer\Ast\QuoteNode;
use Phel\Transpiler\Domain\Analyzer\Ast\RecurFrame;
use Phel\Transpiler\Domain\Analyzer\Ast\RecurNode;
use Phel\Transpiler\Domain\Analyzer\Ast\ThrowNode;
use Phel\Transpiler\Domain\Analyzer\Ast\TryNode;
use Phel\Transpiler\Domain\Analyzer\Environment\GlobalEnvironment;
use Phel\Transpiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Transpiler\Domain\Analyzer\TypeAnalyzer\AnalyzePersistentList;
use PHPUnit\Framework\TestCase;

final class AnalyzePersistentListTest extends TestCase
{
    private AnalyzePersistentList $listAnalyzer;

    protected function setUp(): void
    {
        $this->listAnalyzer = new AnalyzePersistentList(new Analyzer(new GlobalEnvironment()));
    }

    public function test_symbol_with_name_def(): void
    {
        $list = TypeFactory::getInstance()->persistentListFromArray([
            Symbol::create(Symbol::NAME_DEF),
            Symbol::create('increment'),
            'inc',
        ]);
        self::assertInstanceOf(DefNode::class, $this->listAnalyzer->analyze($list, NodeEnvironment::empty()));
    }

    public function test_symbol_with_name_ns(): void
    {
        $list = TypeFactory::getInstance()->persistentListFromArray([
            Symbol::create(Symbol::NAME_NS),
            Symbol::create('def-ns'),
        ]);
        self::assertInstanceOf(NsNode::class, $this->listAnalyzer->analyze($list, NodeEnvironment::empty()));
    }

    public function test_symbol_with_name_fn(): void
    {
        $list = TypeFactory::getInstance()->persistentListFromArray([
            Symbol::create(Symbol::NAME_FN),
            TypeFactory::getInstance()->persistentVectorFromArray([]),
        ]);
        self::assertInstanceOf(FnNode::class, $this->listAnalyzer->analyze($list, NodeEnvironment::empty()));
    }

    public function test_symbol_with_name_quote(): void
    {
        $list = TypeFactory::getInstance()->persistentListFromArray([
            Symbol::create(Symbol::NAME_QUOTE),
             'any text',
        ]);
        self::assertInstanceOf(QuoteNode::class, $this->listAnalyzer->analyze($list, NodeEnvironment::empty()));
    }

    public function test_symbol_with_name_do(): void
    {
        $list = TypeFactory::getInstance()->persistentListFromArray([
            Symbol::create(Symbol::NAME_DO), 1,
        ]);
        self::assertInstanceOf(DoNode::class, $this->listAnalyzer->analyze($list, NodeEnvironment::empty()));
    }

    public function test_symbol_with_name_if(): void
    {
        $list = TypeFactory::getInstance()->persistentListFromArray([
            Symbol::create(Symbol::NAME_IF),
            true,
            true,
        ]);
        self::assertInstanceOf(IfNode::class, $this->listAnalyzer->analyze($list, NodeEnvironment::empty()));
    }

    public function test_symbol_with_name_apply(): void
    {
        $list = TypeFactory::getInstance()->persistentListFromArray([
            Symbol::create(Symbol::NAME_APPLY), '+', TypeFactory::getInstance()->persistentVectorFromArray(['']),
        ]);
        self::assertInstanceOf(ApplyNode::class, $this->listAnalyzer->analyze($list, NodeEnvironment::empty()));
    }

    public function test_symbol_with_name_let(): void
    {
        $list = TypeFactory::getInstance()->persistentListFromArray([
            Symbol::create(Symbol::NAME_LET),
            TypeFactory::getInstance()->persistentVectorFromArray([]),
            TypeFactory::getInstance()->persistentVectorFromArray([]),
        ]);
        self::assertInstanceOf(LetNode::class, $this->listAnalyzer->analyze($list, NodeEnvironment::empty()));
    }

    public function test_symbol_with_name_php_new(): void
    {
        $list = TypeFactory::getInstance()->persistentListFromArray([
            Symbol::create(Symbol::NAME_PHP_NEW), '',
        ]);
        self::assertInstanceOf(PhpNewNode::class, $this->listAnalyzer->analyze($list, NodeEnvironment::empty()));
    }

    public function test_symbol_with_name_php_object_call(): void
    {
        $list = TypeFactory::getInstance()->persistentListFromArray([
            Symbol::create(Symbol::NAME_PHP_OBJECT_CALL), '', Symbol::create(''),
        ]);
        self::assertInstanceOf(
            PhpObjectCallNode::class,
            $this->listAnalyzer->analyze($list, NodeEnvironment::empty()),
        );
    }

    public function test_symbol_with_name_php_object_static_call(): void
    {
        $list = TypeFactory::getInstance()->persistentListFromArray([
            Symbol::create(Symbol::NAME_PHP_OBJECT_STATIC_CALL), '', Symbol::create(''),
        ]);
        self::assertInstanceOf(
            PhpObjectCallNode::class,
            $this->listAnalyzer->analyze($list, NodeEnvironment::empty()),
        );
    }

    public function test_symbol_with_name_php_array_get(): void
    {
        $list = TypeFactory::getInstance()->persistentListFromArray([
            Symbol::create(Symbol::NAME_PHP_ARRAY_GET),
            TypeFactory::getInstance()->persistentListFromArray([Symbol::create('php/array')]),
            0,
        ]);
        self::assertInstanceOf(
            PhpArrayGetNode::class,
            $this->listAnalyzer->analyze($list, NodeEnvironment::empty()),
        );
    }

    public function test_symbol_with_name_php_array_set(): void
    {
        $list = TypeFactory::getInstance()->persistentListFromArray([
            Symbol::create(Symbol::NAME_PHP_ARRAY_SET),
            TypeFactory::getInstance()->persistentListFromArray([Symbol::create('php/array')]),
            0,
            1,
        ]);
        self::assertInstanceOf(
            PhpArraySetNode::class,
            $this->listAnalyzer->analyze($list, NodeEnvironment::empty()),
        );
    }

    public function test_symbol_with_name_php_array_push(): void
    {
        $list = TypeFactory::getInstance()->persistentListFromArray([
            Symbol::create(Symbol::NAME_PHP_ARRAY_PUSH),
            TypeFactory::getInstance()->persistentListFromArray([Symbol::create('php/array')]),
            1,
        ]);
        self::assertInstanceOf(
            PhpArrayPushNode::class,
            $this->listAnalyzer->analyze($list, NodeEnvironment::empty()),
        );
    }

    public function test_symbol_with_name_php_array_unset(): void
    {
        $list = TypeFactory::getInstance()->persistentListFromArray([
            Symbol::create(Symbol::NAME_PHP_ARRAY_UNSET),
            TypeFactory::getInstance()->persistentListFromArray([Symbol::create('php/array')]),
            0,
        ]);
        self::assertInstanceOf(
            PhpArrayUnsetNode::class,
            $this->listAnalyzer->analyze($list, NodeEnvironment::empty()),
        );
    }

    public function test_symbol_with_name_recur(): void
    {
        $list = TypeFactory::getInstance()->persistentListFromArray([
            Symbol::create(Symbol::NAME_RECUR), 1,
        ]);
        $recurFrames = [new RecurFrame([Symbol::create(Symbol::NAME_FOREACH)])];
        $nodeEnv = new NodeEnvironment([], NodeEnvironment::CONTEXT_STATEMENT, [], $recurFrames);
        self::assertInstanceOf(RecurNode::class, $this->listAnalyzer->analyze($list, $nodeEnv));
    }

    public function test_symbol_with_name_try(): void
    {
        $list = TypeFactory::getInstance()->persistentListFromArray([
            Symbol::create(Symbol::NAME_TRY),
        ]);
        self::assertInstanceOf(TryNode::class, $this->listAnalyzer->analyze($list, NodeEnvironment::empty()));
    }

    public function test_symbol_with_name_throw(): void
    {
        $list = TypeFactory::getInstance()->persistentListFromArray([
            Symbol::create(Symbol::NAME_THROW), '',
        ]);
        self::assertInstanceOf(ThrowNode::class, $this->listAnalyzer->analyze($list, NodeEnvironment::empty()));
    }

    public function test_symbol_with_name_loop(): void
    {
        $list = TypeFactory::getInstance()->persistentListFromArray([
            Symbol::create(Symbol::NAME_LOOP),
            TypeFactory::getInstance()->persistentVectorFromArray([]),
            TypeFactory::getInstance()->persistentVectorFromArray([]),
        ]);
        self::assertInstanceOf(LetNode::class, $this->listAnalyzer->analyze($list, NodeEnvironment::empty()));
    }

    public function test_symbol_with_name_foreach(): void
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

    public function test_symbol_with_name_def_struct(): void
    {
        $list = TypeFactory::getInstance()->persistentListFromArray([
            Symbol::create(Symbol::NAME_DEF_STRUCT),
            Symbol::create(''),
            TypeFactory::getInstance()->persistentVectorFromArray([]),
        ]);
        self::assertInstanceOf(DefStructNode::class, $this->listAnalyzer->analyze($list, NodeEnvironment::empty()));
    }
}
