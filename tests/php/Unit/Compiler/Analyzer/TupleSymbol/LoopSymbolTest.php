<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\TupleSymbol;

use Phel\Compiler\Analyzer\Analyzer;
use Phel\Compiler\Analyzer\AnalyzerInterface;
use Phel\Compiler\Analyzer\Ast\BindingNode;
use Phel\Compiler\Analyzer\Ast\DoNode;
use Phel\Compiler\Analyzer\Ast\LetNode;
use Phel\Compiler\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Analyzer\Ast\RecurFrame;
use Phel\Compiler\Analyzer\Environment\GlobalEnvironment;
use Phel\Compiler\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Analyzer\TypeAnalyzer\TupleSymbol\Binding\BindingValidator;
use Phel\Compiler\Analyzer\TypeAnalyzer\TupleSymbol\LoopSymbol;
use Phel\Lang\Symbol;
use Phel\Lang\Table;
use Phel\Lang\Tuple;
use PHPUnit\Framework\TestCase;

final class LoopSymbolTest extends TestCase
{
    private AnalyzerInterface $analyzer;

    public function setUp(): void
    {
        $env = new GlobalEnvironment();
        $env->addDefinition('phel\\core', Symbol::create('first'), new Table());
        $env->addDefinition('phel\\core', Symbol::create('next'), new Table());
        $this->analyzer = new Analyzer($env);
    }

    public function testWrongSymbolName(): void
    {
        $this->expectException(AnalyzerException::class);
        $this->expectExceptionMessage("This is not a 'loop.");

        $tuple = Tuple::create(Symbol::create('unknown'));
        $env = NodeEnvironment::empty();

        (new LoopSymbol($this->analyzer, new BindingValidator()))->analyze($tuple, $env);
    }

    public function testWrongNumberOfArguments(): void
    {
        $this->expectException(AnalyzerException::class);
        $this->expectExceptionMessage("At least two arguments are required for 'loop.");

        $tuple = Tuple::create(Symbol::create('loop'));
        $env = NodeEnvironment::empty();

        $this->analyzer->analyze($tuple, $env);
    }

    public function testWrongBindingParameter(): void
    {
        $this->expectException(AnalyzerException::class);
        $this->expectExceptionMessage('Binding parameter must be a tuple');

        $tuple = Tuple::create(Symbol::create('loop'), 12);
        $env = NodeEnvironment::empty();

        $this->analyzer->analyze($tuple, $env);
    }

    public function testUnevenBindings(): void
    {
        $this->expectException(AnalyzerException::class);
        $this->expectExceptionMessage('Bindings must be a even number of parameters');

        $tuple = Tuple::create(Symbol::create('loop'), Tuple::create(12));
        $env = NodeEnvironment::empty();

        $this->analyzer->analyze($tuple, $env);
    }

    public function testBasicLoop(): void
    {
        $tuple = Tuple::create(Symbol::create('loop'), Tuple::create());
        $env = NodeEnvironment::empty();

        $this->assertEquals(
            new LetNode(
                $env,
                [],
                new DoNode(
                    $env->withAddedRecurFrame(new RecurFrame([])),
                    [],
                    new LiteralNode($env->withAddedRecurFrame(new RecurFrame([])), null)
                ),
                false
            ),
            $this->analyzer->analyze($tuple, $env)
        );
    }

    public function testLoopWithBinding(): void
    {
        Symbol::resetGen();
        $tuple = Tuple::create(Symbol::create('loop'), Tuple::create(Symbol::create('a'), 1));
        $env = NodeEnvironment::empty();

        $this->assertEquals(
            new LetNode(
                $env,
                [
                    new BindingNode(
                        $env->withDisallowRecurFrame(),
                        Symbol::create('a'),
                        Symbol::create('a_1'),
                        new LiteralNode(
                            $env->withContext(NodeEnvironment::CONTEXT_EXPRESSION)->withDisallowRecurFrame()->withDisallowRecurFrame()->withBoundTo('.a'),
                            1
                        )
                    ),
                ],
                new DoNode(
                    $env->withAddedRecurFrame(new RecurFrame([Symbol::create('a')]))
                        ->withLocals([Symbol::create('a')])
                        ->withShadowedLocal(Symbol::create('a'), Symbol::create('a_1')),
                    [],
                    new LiteralNode(
                        $env->withAddedRecurFrame(new RecurFrame([Symbol::create('a')]))
                            ->withLocals([Symbol::create('a')])
                            ->withShadowedLocal(Symbol::create('a'), Symbol::create('a_1')),
                        null
                    )
                ),
                false
            ),
            $this->analyzer->analyze($tuple, $env)
        );
    }

    public function testWithDestruction(): void
    {
        Symbol::resetGen();
        $tuple = Tuple::create(Symbol::create('loop'), Tuple::create(Tuple::create(Symbol::create('a')), 1), 1);
        $env = NodeEnvironment::empty()->withContext(NodeEnvironment::CONTEXT_EXPRESSION);

        /** @var LetNode $node */
        $node = $this->analyzer->analyze($tuple, $env);
        $this->assertInstanceOf(LetNode::class, $node);
        $this->assertFalse($node->isLoop());
        $this->assertEquals(1, count($node->getBindings()));

        /** @var DoNode $bodyNode */
        $bodyNode = $node->getBodyExpr();
        $this->assertInstanceOf(DoNode::class, $bodyNode);

        /** @var LetNode $letNode */
        $letNode = $bodyNode->getRet();
        $this->assertInstanceOf(LetNode::class, $letNode);
        $this->assertFalse($letNode->isLoop());
    }
}
