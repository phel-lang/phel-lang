<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\SpecialForm;

use Phel;
use Phel\Compiler\Application\Analyzer;
use Phel\Compiler\Domain\Analyzer\Ast\DoNode;
use Phel\Compiler\Domain\Analyzer\Ast\ForeachNode;
use Phel\Compiler\Domain\Analyzer\Ast\LetNode;
use Phel\Compiler\Domain\Analyzer\Ast\LocalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\MapNode;
use Phel\Compiler\Domain\Analyzer\Ast\VectorNode;
use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironment;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\ForeachSymbol;
use Phel\Compiler\Domain\Exceptions\AbstractLocatedException;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Symbol;
use PHPUnit\Framework\TestCase;

final class ForeachSymbolTest extends TestCase
{
    public function test_requires_at_least_two_arg(): void
    {
        $this->expectException(AbstractLocatedException::class);
        $this->expectExceptionMessage("At least two arguments are required for 'foreach");

        // (foreach)
        $list = Phel::list([
            Symbol::create(Symbol::NAME_FOREACH),
        ]);

        $this->analyze($list);
    }

    public function test_first_arg_must_be_a_vector(): void
    {
        $this->expectException(AbstractLocatedException::class);
        $this->expectExceptionMessage("First argument of 'foreach must be a vector.");

        // (foreach x)
        $list = Phel::list([
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
        $list = Phel::list([
            Symbol::create(Symbol::NAME_FOREACH),
            Phel::vector([
                Symbol::create('x'),
            ]),
        ]);

        $this->analyze($list);
    }

    public function test_value_symbol_from_vector_with2_args(): void
    {
        // (foreach [x []])
        $list = Phel::list([
            Symbol::create(Symbol::NAME_FOREACH),
            Phel::vector([
                Symbol::create('x'),
                Phel::vector([]),
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
        $list = Phel::list([
            Symbol::create(Symbol::NAME_FOREACH),
            Phel::vector([
                Phel::vector([Symbol::create('x')]),
                Phel::vector([]),
            ]),
            Symbol::create('x'),
        ]);

        $node = $this->analyze($list);
        self::assertInstanceOf(LetNode::class, $node->getBodyExpr());
    }

    public function test_value_symbol_vector_with3_args(): void
    {
        // (foreach [key value {}])
        $list = Phel::list([
            Symbol::create(Symbol::NAME_FOREACH),
            Phel::vector([
                Symbol::create('key'),
                Symbol::create('value'),
                Phel::map(),
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
        $list = Phel::list([
            Symbol::create(Symbol::NAME_FOREACH),
            Phel::vector([
                Phel::vector([Symbol::create('key')]),
                Phel::vector([Symbol::create('value')]),
                Phel::vector([]),
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
        $list = Phel::list([
            Symbol::create(Symbol::NAME_FOREACH),
            Phel::vector([
                Symbol::create('x'),
                Symbol::create('y'),
                Symbol::create('z'),
                Phel::map(),
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
