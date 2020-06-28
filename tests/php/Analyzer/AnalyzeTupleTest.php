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
    /**
     * @dataProvider tupleProvider
     */
    public function testSymbols(string $expected, Tuple $tuple): void
    {
        $tupleAnalyzer = new AnalyzeTuple(new Analyzer(new GlobalEnvironment()));
        self::assertInstanceOf($expected, $tupleAnalyzer($tuple, NodeEnvironment::empty()));
    }

    public function tupleProvider(): array
    {
        return [
            Symbol::NAME_DEF => [
                DefNode::class,
                Tuple::create(Symbol::create(Symbol::NAME_DEF), Symbol::create('increment'), 'inc')
            ],
            Symbol::NAME_NS => [
                NsNode::class,
                Tuple::create(Symbol::create(Symbol::NAME_NS), Symbol::create('def-ns'))
            ],
            Symbol::NAME_FN => [
                FnNode::class,
                Tuple::create(Symbol::create(Symbol::NAME_FN), Tuple::create())
            ],
            Symbol::NAME_QUOTE => [
                QuoteNode::class,
                Tuple::create(Symbol::create(Symbol::NAME_QUOTE), 'any text')
            ],
            Symbol::NAME_DO => [
                DoNode::class,
                Tuple::create(Symbol::create(Symbol::NAME_DO), 1)
            ],
            Symbol::NAME_IF => [
                IfNode::class,
                Tuple::create(Symbol::create(Symbol::NAME_IF), true, true)
            ],
            Symbol::NAME_APPLY => [
                ApplyNode::class,
                Tuple::create(Symbol::create(Symbol::NAME_APPLY), '+', Tuple::create(''))
            ],
            Symbol::NAME_LET => [
                LetNode::class,
                Tuple::create(Symbol::create(Symbol::NAME_LET), Tuple::create(), Tuple::create())
            ],
            Symbol::NAME_PHP_NEW => [PhpNewNode::class, Tuple::create(Symbol::create(Symbol::NAME_PHP_NEW), '')],
            Symbol::NAME_PHP_OBJECT_CALL => [
                PhpObjectCallNode::class,
                Tuple::create(Symbol::create(Symbol::NAME_PHP_OBJECT_CALL), '', Symbol::create(''))
            ],
            Symbol::NAME_PHP_OBJECT_STATIC_CALL => [
                PhpObjectCallNode::class,
                Tuple::create(Symbol::create(Symbol::NAME_PHP_OBJECT_STATIC_CALL), '', Symbol::create(''))
            ],
            Symbol::NAME_PHP_ARRAY_GET => [
                PhpArrayGetNode::class,
                Tuple::create(Symbol::create(Symbol::NAME_PHP_ARRAY_GET))
            ],
            Symbol::NAME_PHP_ARRAY_SET => [
                PhpArraySetNode::class,
                Tuple::create(Symbol::create(Symbol::NAME_PHP_ARRAY_SET))
            ],
            Symbol::NAME_PHP_ARRAY_PUSH => [
                PhpArrayPushNode::class,
                Tuple::create(Symbol::create(Symbol::NAME_PHP_ARRAY_PUSH))
            ],
            Symbol::NAME_PHP_ARRAY_UNSET => [
                PhpArrayUnsetNode::class,
                Tuple::create(Symbol::create(Symbol::NAME_PHP_ARRAY_UNSET))
            ],
            Symbol::NAME_TRY => [
                TryNode::class,
                Tuple::create(Symbol::create(Symbol::NAME_TRY))
            ],
            Symbol::NAME_THROW => [
                ThrowNode::class,
                Tuple::create(Symbol::create(Symbol::NAME_THROW), '')
            ],
            Symbol::NAME_LOOP => [
                LetNode::class,
                Tuple::create(Symbol::create(Symbol::NAME_LOOP), Tuple::create(), Tuple::create())
            ],
            Symbol::NAME_FOREACH => [
                ForeachNode::class,
                Tuple::create(
                    Symbol::create(Symbol::NAME_FOREACH),
                    Tuple::create(Symbol::create(''), Tuple::create()),
                    Tuple::create()
                )
            ],
            Symbol::NAME_DEF_STRUCT => [
                DefStructNode::class,
                Tuple::create(Symbol::create(Symbol::NAME_DEF_STRUCT), Symbol::create(''), Tuple::create())
            ],
        ];
    }

    public function testRecurSymbol(): void
    {
        $recurFrames = [new RecurFrame([Symbol::create(Symbol::NAME_FOREACH)])];
        $nodeEnv = new NodeEnvironment([], NodeEnvironment::CTX_STMT, [], $recurFrames);
        $tupleAnalyzer = new AnalyzeTuple(new Analyzer(new GlobalEnvironment()));

        self::assertInstanceOf(
            RecurNode::class,
            $tupleAnalyzer(Tuple::create(Symbol::create(Symbol::NAME_RECUR), 1), $nodeEnv)
        );
    }
}
