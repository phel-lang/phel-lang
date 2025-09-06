<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer;

use Phel;
use Phel\Compiler\Application\Analyzer;
use Phel\Compiler\Domain\Analyzer\Ast\ApplyNode;
use Phel\Compiler\Domain\Analyzer\Ast\DefExceptionNode;
use Phel\Compiler\Domain\Analyzer\Ast\DefNode;
use Phel\Compiler\Domain\Analyzer\Ast\DefStructNode;
use Phel\Compiler\Domain\Analyzer\Ast\DoNode;
use Phel\Compiler\Domain\Analyzer\Ast\FnNode;
use Phel\Compiler\Domain\Analyzer\Ast\ForeachNode;
use Phel\Compiler\Domain\Analyzer\Ast\IfNode;
use Phel\Compiler\Domain\Analyzer\Ast\LetNode;
use Phel\Compiler\Domain\Analyzer\Ast\NsNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpArrayGetNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpArrayPushNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpArraySetNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpArrayUnsetNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpNewNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpObjectCallNode;
use Phel\Compiler\Domain\Analyzer\Ast\QuoteNode;
use Phel\Compiler\Domain\Analyzer\Ast\RecurFrame;
use Phel\Compiler\Domain\Analyzer\Ast\RecurNode;
use Phel\Compiler\Domain\Analyzer\Ast\ThrowNode;
use Phel\Compiler\Domain\Analyzer\Ast\TryNode;
use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironment;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\AnalyzePersistentList;
use Phel\Lang\Symbol;
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
        $list = Phel::list([
            Symbol::create(Symbol::NAME_DEF),
            Symbol::create('increment'),
            'inc',
        ]);
        self::assertInstanceOf(DefNode::class, $this->listAnalyzer->analyze($list, NodeEnvironment::empty()));
    }

    public function test_symbol_with_name_ns(): void
    {
        $list = Phel::list([
            Symbol::create(Symbol::NAME_NS),
            Symbol::create('def-ns'),
        ]);
        self::assertInstanceOf(NsNode::class, $this->listAnalyzer->analyze($list, NodeEnvironment::empty()));
    }

    public function test_symbol_with_name_fn(): void
    {
        $list = Phel::list([
            Symbol::create(Symbol::NAME_FN),
            Phel::vector([]),
        ]);
        self::assertInstanceOf(FnNode::class, $this->listAnalyzer->analyze($list, NodeEnvironment::empty()));
    }

    public function test_symbol_with_name_quote(): void
    {
        $list = Phel::list([
            Symbol::create(Symbol::NAME_QUOTE),
             'any text',
        ]);
        self::assertInstanceOf(QuoteNode::class, $this->listAnalyzer->analyze($list, NodeEnvironment::empty()));
    }

    public function test_symbol_with_name_do(): void
    {
        $list = Phel::list([
            Symbol::create(Symbol::NAME_DO), 1,
        ]);
        self::assertInstanceOf(DoNode::class, $this->listAnalyzer->analyze($list, NodeEnvironment::empty()));
    }

    public function test_symbol_with_name_if(): void
    {
        $list = Phel::list([
            Symbol::create(Symbol::NAME_IF),
            true,
            true,
        ]);
        self::assertInstanceOf(IfNode::class, $this->listAnalyzer->analyze($list, NodeEnvironment::empty()));
    }

    public function test_symbol_with_name_apply(): void
    {
        $list = Phel::list([
            Symbol::create(Symbol::NAME_APPLY), '+', Phel::vector(['']),
        ]);
        self::assertInstanceOf(ApplyNode::class, $this->listAnalyzer->analyze($list, NodeEnvironment::empty()));
    }

    public function test_symbol_with_name_let(): void
    {
        $list = Phel::list([
            Symbol::create(Symbol::NAME_LET),
            Phel::vector([]),
            Phel::vector([]),
        ]);
        self::assertInstanceOf(LetNode::class, $this->listAnalyzer->analyze($list, NodeEnvironment::empty()));
    }

    public function test_symbol_with_name_php_new(): void
    {
        $list = Phel::list([
            Symbol::create(Symbol::NAME_PHP_NEW), '',
        ]);
        self::assertInstanceOf(PhpNewNode::class, $this->listAnalyzer->analyze($list, NodeEnvironment::empty()));
    }

    public function test_symbol_with_name_php_object_call(): void
    {
        $list = Phel::list([
            Symbol::create(Symbol::NAME_PHP_OBJECT_CALL), '', Symbol::create(''),
        ]);
        self::assertInstanceOf(
            PhpObjectCallNode::class,
            $this->listAnalyzer->analyze($list, NodeEnvironment::empty()),
        );
    }

    public function test_symbol_with_name_php_object_static_call(): void
    {
        $list = Phel::list([
            Symbol::create(Symbol::NAME_PHP_OBJECT_STATIC_CALL), '', Symbol::create(''),
        ]);
        self::assertInstanceOf(
            PhpObjectCallNode::class,
            $this->listAnalyzer->analyze($list, NodeEnvironment::empty()),
        );
    }

    public function test_symbol_with_name_php_array_get(): void
    {
        $list = Phel::list([
            Symbol::create(Symbol::NAME_PHP_ARRAY_GET),
            Phel::list([Symbol::create('php/array')]),
            0,
        ]);
        self::assertInstanceOf(
            PhpArrayGetNode::class,
            $this->listAnalyzer->analyze($list, NodeEnvironment::empty()),
        );
    }

    public function test_symbol_with_name_php_array_get_in(): void
    {
        $list = Phel::list([
            Symbol::create(Symbol::NAME_PHP_ARRAY_GET_IN),
            Phel::list([Symbol::create('php/array')]),
            Phel::vector([0, 1]),
        ]);
        self::assertInstanceOf(
            PhpArrayGetNode::class,
            $this->listAnalyzer->analyze($list, NodeEnvironment::empty()),
        );
    }

    public function test_symbol_with_name_php_array_set(): void
    {
        $list = Phel::list([
            Symbol::create(Symbol::NAME_PHP_ARRAY_SET),
            Phel::list([Symbol::create('php/array')]),
            0,
            1,
        ]);
        self::assertInstanceOf(
            PhpArraySetNode::class,
            $this->listAnalyzer->analyze($list, NodeEnvironment::empty()),
        );
    }

    public function test_symbol_with_name_php_array_set_in(): void
    {
        $list = Phel::list([
            Symbol::create(Symbol::NAME_PHP_ARRAY_SET_IN),
            Phel::list([Symbol::create('php/array')]),
            Phel::vector([0, 1]),
            1,
        ]);
        self::assertInstanceOf(
            PhpArraySetNode::class,
            $this->listAnalyzer->analyze($list, NodeEnvironment::empty()),
        );
    }

    public function test_symbol_with_name_php_array_push(): void
    {
        $list = Phel::list([
            Symbol::create(Symbol::NAME_PHP_ARRAY_PUSH),
            Phel::list([Symbol::create('php/array')]),
            1,
        ]);
        self::assertInstanceOf(
            PhpArrayPushNode::class,
            $this->listAnalyzer->analyze($list, NodeEnvironment::empty()),
        );
    }

    public function test_symbol_with_name_php_array_push_in(): void
    {
        $list = Phel::list([
            Symbol::create(Symbol::NAME_PHP_ARRAY_PUSH_IN),
            Phel::list([Symbol::create('php/array')]),
            Phel::vector([0]),
            1,
        ]);
        self::assertInstanceOf(
            PhpArrayPushNode::class,
            $this->listAnalyzer->analyze($list, NodeEnvironment::empty()),
        );
    }

    public function test_symbol_with_name_php_array_unset(): void
    {
        $list = Phel::list([
            Symbol::create(Symbol::NAME_PHP_ARRAY_UNSET),
            Phel::list([Symbol::create('php/array')]),
            0,
        ]);
        self::assertInstanceOf(
            PhpArrayUnsetNode::class,
            $this->listAnalyzer->analyze($list, NodeEnvironment::empty()),
        );
    }

    public function test_symbol_with_name_php_array_unset_in(): void
    {
        $list = Phel::list([
            Symbol::create(Symbol::NAME_PHP_ARRAY_UNSET_IN),
            Phel::list([Symbol::create('php/array')]),
            Phel::vector([0, 1]),
        ]);
        self::assertInstanceOf(
            PhpArrayUnsetNode::class,
            $this->listAnalyzer->analyze($list, NodeEnvironment::empty()),
        );
    }

    public function test_symbol_with_name_recur(): void
    {
        $list = Phel::list([
            Symbol::create(Symbol::NAME_RECUR), 1,
        ]);
        $recurFrames = [new RecurFrame([Symbol::create(Symbol::NAME_FOREACH)])];
        $nodeEnv = new NodeEnvironment([], NodeEnvironment::CONTEXT_STATEMENT, [], $recurFrames);
        self::assertInstanceOf(RecurNode::class, $this->listAnalyzer->analyze($list, $nodeEnv));
    }

    public function test_symbol_with_name_try(): void
    {
        $list = Phel::list([
            Symbol::create(Symbol::NAME_TRY),
        ]);
        self::assertInstanceOf(TryNode::class, $this->listAnalyzer->analyze($list, NodeEnvironment::empty()));
    }

    public function test_symbol_with_name_throw(): void
    {
        $list = Phel::list([
            Symbol::create(Symbol::NAME_THROW), '',
        ]);
        self::assertInstanceOf(ThrowNode::class, $this->listAnalyzer->analyze($list, NodeEnvironment::empty()));
    }

    public function test_symbol_with_name_loop(): void
    {
        $list = Phel::list([
            Symbol::create(Symbol::NAME_LOOP),
            Phel::vector([]),
            Phel::vector([]),
        ]);
        self::assertInstanceOf(LetNode::class, $this->listAnalyzer->analyze($list, NodeEnvironment::empty()));
    }

    public function test_symbol_with_name_foreach(): void
    {
        $list = Phel::list([
            Symbol::create(Symbol::NAME_FOREACH),
            Phel::vector([
                Symbol::create(''), Phel::vector([]),
            ]),
            Phel::vector([]),
        ]);
        self::assertInstanceOf(ForeachNode::class, $this->listAnalyzer->analyze($list, NodeEnvironment::empty()));
    }

    public function test_symbol_with_name_def_struct(): void
    {
        $list = Phel::list([
            Symbol::create(Symbol::NAME_DEF_STRUCT),
            Symbol::create(''),
            Phel::vector([]),
        ]);
        self::assertInstanceOf(DefStructNode::class, $this->listAnalyzer->analyze($list, NodeEnvironment::empty()));
    }

    public function test_symbol_with_name_def_exception(): void
    {
        $list = Phel::list([
            Symbol::create(Symbol::NAME_DEF_EXCEPTION),
            Symbol::create('MyExc'),
        ]);
        self::assertInstanceOf(DefExceptionNode::class, $this->listAnalyzer->analyze($list, NodeEnvironment::empty()));
    }
}
