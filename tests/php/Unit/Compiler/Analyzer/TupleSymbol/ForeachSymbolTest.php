<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\TupleSymbol;

use Phel\Compiler\Analyzer\Analyzer;
use Phel\Compiler\Analyzer\Ast\DoNode;
use Phel\Compiler\Analyzer\TypeAnalyzer\TupleSymbol\ForeachSymbol;
use Phel\Compiler\Analyzer\Ast\ForeachNode;
use Phel\Compiler\Analyzer\Ast\LetNode;
use Phel\Compiler\Analyzer\Ast\LocalVarNode;
use Phel\Compiler\Analyzer\Ast\TableNode;
use Phel\Compiler\Analyzer\Ast\TupleNode;
use Phel\Compiler\Analyzer\Environment\NodeEnvironment;
use Phel\Exceptions\PhelCodeException;
use Phel\Compiler\Analyzer\Environment\GlobalEnvironment;
use Phel\Lang\Symbol;
use Phel\Lang\Table;
use Phel\Lang\Tuple;
use PHPUnit\Framework\TestCase;

final class ForeachSymbolTest extends TestCase
{
    public function testRequiresAtLeastTwoArg(): void
    {
        $this->expectException(PhelCodeException::class);
        $this->expectExceptionMessage("At least two arguments are required for 'foreach");

        // (foreach)
        $tuple = Tuple::create(
            Symbol::create(Symbol::NAME_FOREACH)
        );

        $this->analyze($tuple);
    }

    public function testFirstArgMustBeATuple(): void
    {
        $this->expectException(PhelCodeException::class);
        $this->expectExceptionMessage("First argument of 'foreach must be a tuple.");

        // (foreach x)
        $mainTuple = Tuple::create(
            Symbol::create(Symbol::NAME_FOREACH),
            Symbol::create('x')
        );

        $this->analyze($mainTuple);
    }

    public function testArgForTupleCanNotBe1(): void
    {
        $this->expectException(PhelCodeException::class);
        $this->expectExceptionMessage("Tuple of 'foreach must have exactly two or three elements.");

        // (foreach [x])
        $mainTuple = Tuple::create(
            Symbol::create(Symbol::NAME_FOREACH),
            Tuple::createBracket(
                Symbol::create('x')
            )
        );

        $this->analyze($mainTuple);
    }

    public function testValueSymbolFromTupleWith2Args(): void
    {
        // (foreach [x []])
        $mainTuple = Tuple::create(
            Symbol::create(Symbol::NAME_FOREACH),
            Tuple::createBracket(
                Symbol::create('x'),
                Tuple::createBracket()
            ),
            Symbol::create('x')
        );

        $env = NodeEnvironment::empty();

        self::assertEquals(
            new ForeachNode(
                $env,
                new DoNode(
                    $env->withLocals([Symbol::create('x')]),
                    [],
                    new LocalVarNode($env->withLocals([Symbol::create('x')]), Symbol::create('x'))
                ),
                new TupleNode($env->withContext(NodeEnvironment::CONTEXT_EXPRESSION), []),
                Symbol::create('x')
            ),
            $this->analyze($mainTuple)
        );
    }

    public function testDeconstrutionWithTwoArgs(): void
    {
        // (foreach [[x] []])
        $mainTuple = Tuple::create(
            Symbol::create(Symbol::NAME_FOREACH),
            Tuple::createBracket(
                Tuple::createBracket(Symbol::create('x')),
                Tuple::createBracket()
            ),
            Symbol::create('x')
        );

        $node = $this->analyze($mainTuple);
        self::assertInstanceOf(LetNode::class, $node->getBodyExpr());
    }

    public function testValueSymbolFromTupleWith3Args(): void
    {
        // (foreach [key value @{}])
        $mainTuple = Tuple::create(
            Symbol::create(Symbol::NAME_FOREACH),
            Tuple::createBracket(
                Symbol::create('key'),
                Symbol::create('value'),
                Table::empty()
            ),
            Symbol::create('key')
        );

        $env = NodeEnvironment::empty();

        self::assertEquals(
            new ForeachNode(
                $env,
                new DoNode(
                    $env->withLocals([Symbol::create('value'), Symbol::create('key')]),
                    [],
                    new LocalVarNode($env->withLocals([Symbol::create('value'), Symbol::create('key')]), Symbol::create('key'))
                ),
                new TableNode($env->withContext(NodeEnvironment::CONTEXT_EXPRESSION), []),
                Symbol::create('value'),
                Symbol::create('key')
            ),
            $this->analyze($mainTuple)
        );
    }

    public function testDeconstrutionWithThreeArgs(): void
    {
        // (foreach [[key] [value] []])
        $mainTuple = Tuple::create(
            Symbol::create(Symbol::NAME_FOREACH),
            Tuple::createBracket(
                Tuple::createBracket(Symbol::create('key')),
                Tuple::createBracket(Symbol::create('value')),
                Tuple::createBracket()
            ),
            Symbol::create('key')
        );

        $node = $this->analyze($mainTuple);
        self::assertInstanceOf(LetNode::class, $node->getBodyExpr());
    }

    public function testArgForTupleCanNotBe4(): void
    {
        $this->expectException(PhelCodeException::class);
        $this->expectExceptionMessage("Tuple of 'foreach must have exactly two or three elements.");

        // (foreach [x y z @{}])
        $mainTuple = Tuple::create(
            Symbol::create(Symbol::NAME_FOREACH),
            Tuple::create(
                Symbol::create('x'),
                Symbol::create('y'),
                Symbol::create('z'),
                Table::empty()
            )
        );

        $this->analyze($mainTuple);
    }

    private function analyze(Tuple $tuple): ForeachNode
    {
        $env = new GlobalEnvironment();
        $env->addDefinition('phel\\core', Symbol::create('first'), new Table());
        $env->addDefinition('phel\\core', Symbol::create('next'), new Table());
        $analyzer = new Analyzer($env);

        return (new ForeachSymbol($analyzer))->analyze($tuple, NodeEnvironment::empty());
    }
}
