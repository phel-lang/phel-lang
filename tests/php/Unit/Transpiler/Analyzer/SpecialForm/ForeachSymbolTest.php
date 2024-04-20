<?php

declare(strict_types=1);

namespace PhelTest\Unit\Transpiler\Analyzer\SpecialForm;

use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Symbol;
use Phel\Lang\TypeFactory;
use Phel\Transpiler\Domain\Analyzer\Analyzer;
use Phel\Transpiler\Domain\Analyzer\Ast\DoNode;
use Phel\Transpiler\Domain\Analyzer\Ast\ForeachNode;
use Phel\Transpiler\Domain\Analyzer\Ast\LetNode;
use Phel\Transpiler\Domain\Analyzer\Ast\LocalVarNode;
use Phel\Transpiler\Domain\Analyzer\Ast\MapNode;
use Phel\Transpiler\Domain\Analyzer\Ast\VectorNode;
use Phel\Transpiler\Domain\Analyzer\Environment\GlobalEnvironment;
use Phel\Transpiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Transpiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\ForeachSymbol;
use Phel\Transpiler\Domain\Exceptions\AbstractLocatedException;
use PHPUnit\Framework\TestCase;

final class ForeachSymbolTest extends TestCase
{
    public function test_requires_at_least_two_arg(): void
    {
        $this->expectException(AbstractLocatedException::class);
        $this->expectExceptionMessage("At least two arguments are required for 'foreach");

        // (foreach)
        $list = TypeFactory::getInstance()->persistentListFromArray([
            Symbol::create(Symbol::NAME_FOREACH),
        ]);

        $this->analyze($list);
    }

    public function test_first_arg_must_be_a_vector(): void
    {
        $this->expectException(AbstractLocatedException::class);
        $this->expectExceptionMessage("First argument of 'foreach must be a vector.");

        // (foreach x)
        $list = TypeFactory::getInstance()->persistentListFromArray([
            Symbol::create(Symbol::NAME_FOREACH),
            Symbol::create('x'),
        ]);

        $this->analyze($list);
    }

    public function test_arg_for_vector_can_not_be1(): void
    {
        $this->expectException(AbstractLocatedException::class);
        $this->expectExceptionMessage("Vector of 'foreach must have exactly two or three elements.");

        // (foreach [x])
        $list = TypeFactory::getInstance()->persistentListFromArray([
            Symbol::create(Symbol::NAME_FOREACH),
            TypeFactory::getInstance()->persistentVectorFromArray([
                Symbol::create('x'),
            ]),
        ]);

        $this->analyze($list);
    }

    public function test_value_symbol_from_vector_with2_args(): void
    {
        // (foreach [x []])
        $list = TypeFactory::getInstance()->persistentListFromArray([
            Symbol::create(Symbol::NAME_FOREACH),
            TypeFactory::getInstance()->persistentVectorFromArray([
                Symbol::create('x'),
                TypeFactory::getInstance()->persistentVectorFromArray([]),
            ]),
            Symbol::create('x'),
        ]);

        $env = NodeEnvironment::empty();

        self::assertEquals(
            new ForeachNode(
                $env,
                new DoNode(
                    $env->withLocals([Symbol::create('x')]),
                    [],
                    new LocalVarNode($env->withLocals([Symbol::create('x')]), Symbol::create('x')),
                ),
                new VectorNode($env->withExpressionContext(), []),
                Symbol::create('x'),
            ),
            $this->analyze($list),
        );
    }

    public function test_deconstrution_with_two_args(): void
    {
        // (foreach [[x] []])
        $list = TypeFactory::getInstance()->persistentListFromArray([
            Symbol::create(Symbol::NAME_FOREACH),
            TypeFactory::getInstance()->persistentVectorFromArray([
                TypeFactory::getInstance()->persistentVectorFromArray([Symbol::create('x')]),
                TypeFactory::getInstance()->persistentVectorFromArray([]),
            ]),
            Symbol::create('x'),
        ]);

        $node = $this->analyze($list);
        self::assertInstanceOf(LetNode::class, $node->getBodyExpr());
    }

    public function test_value_symbol_vector_with3_args(): void
    {
        // (foreach [key value {}])
        $list = TypeFactory::getInstance()->persistentListFromArray([
            Symbol::create(Symbol::NAME_FOREACH),
            TypeFactory::getInstance()->persistentVectorFromArray([
                Symbol::create('key'),
                Symbol::create('value'),
                TypeFactory::getInstance()->emptyPersistentMap(),
            ]),
            Symbol::create('key'),
        ]);

        $env = NodeEnvironment::empty();

        self::assertEquals(
            new ForeachNode(
                $env,
                new DoNode(
                    $env->withLocals([Symbol::create('value'), Symbol::create('key')]),
                    [],
                    new LocalVarNode($env->withLocals([Symbol::create('value'), Symbol::create('key')]), Symbol::create('key')),
                ),
                new MapNode($env->withExpressionContext(), []),
                Symbol::create('value'),
                Symbol::create('key'),
            ),
            $this->analyze($list),
        );
    }

    public function test_deconstrution_with_three_args(): void
    {
        // (foreach [[key] [value] []])
        $list = TypeFactory::getInstance()->persistentListFromArray([
            Symbol::create(Symbol::NAME_FOREACH),
            TypeFactory::getInstance()->persistentVectorFromArray([
                TypeFactory::getInstance()->persistentVectorFromArray([Symbol::create('key')]),
                TypeFactory::getInstance()->persistentVectorFromArray([Symbol::create('value')]),
                TypeFactory::getInstance()->persistentVectorFromArray([]),
            ]),
            Symbol::create('key'),
        ]);

        $node = $this->analyze($list);
        self::assertInstanceOf(LetNode::class, $node->getBodyExpr());
    }

    public function test_arg_for_vector_can_not_be4(): void
    {
        $this->expectException(AbstractLocatedException::class);
        $this->expectExceptionMessage("Vector of 'foreach must have exactly two or three elements.");

        // (foreach [x y z {}])
        $list = TypeFactory::getInstance()->persistentListFromArray([
            Symbol::create(Symbol::NAME_FOREACH),
            TypeFactory::getInstance()->persistentVectorFromArray([
                Symbol::create('x'),
                Symbol::create('y'),
                Symbol::create('z'),
                TypeFactory::getInstance()->emptyPersistentMap(),
            ]),
        ]);

        $this->analyze($list);
    }

    private function analyze(PersistentListInterface $list): ForeachNode
    {
        $env = new GlobalEnvironment();
        $env->addDefinition('phel\\core', Symbol::create('first'));
        $env->addDefinition('phel\\core', Symbol::create('next'));

        $analyzer = new Analyzer($env);

        return (new ForeachSymbol($analyzer))->analyze($list, NodeEnvironment::empty());
    }
}
