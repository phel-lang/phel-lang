<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\TupleSymbol;

use Phel\Compiler\Analyzer\Analyzer;
use Phel\Compiler\Analyzer\AnalyzerInterface;
use Phel\Compiler\Analyzer\Ast\BindingNode;
use Phel\Compiler\Analyzer\Ast\DoNode;
use Phel\Compiler\Analyzer\Ast\LetNode;
use Phel\Compiler\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Analyzer\Environment\GlobalEnvironment;
use Phel\Compiler\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Analyzer\TypeAnalyzer\TupleSymbol\Binding\TupleDeconstructorInterface;
use Phel\Compiler\Analyzer\TypeAnalyzer\TupleSymbol\LetSymbol;
use Phel\Exceptions\AnalyzerException;
use Phel\Lang\Symbol;
use Phel\Lang\Tuple;
use PHPUnit\Framework\TestCase;

final class LetSymbolTest extends TestCase
{
    private AnalyzerInterface $analyzer;

    public function setUp(): void
    {
        $this->analyzer = new Analyzer(new GlobalEnvironment());
    }

    public function testWrongSymbolName(): void
    {
        $this->expectException(AnalyzerException::class);
        $this->expectExceptionMessage("This is not a 'let.");

        $tuple = Tuple::create(Symbol::create('unknown'));
        $env = NodeEnvironment::empty();

        $analyzer = new LetSymbol($this->analyzer, $this->createMock(TupleDeconstructorInterface::class));

        $analyzer->analyze($tuple, $env);
    }

    public function testWrongArguments(): void
    {
        $this->expectException(AnalyzerException::class);
        $this->expectExceptionMessage("At least two arguments are required for 'let");

        $tuple = Tuple::create(Symbol::create('let'));
        $env = NodeEnvironment::empty();

        $this->analyzer->analyze($tuple, $env);
    }

    public function testWrongSecondArgument(): void
    {
        $this->expectException(AnalyzerException::class);
        $this->expectExceptionMessage('Binding parameter must be a tuple');

        $tuple = Tuple::create(Symbol::create('let'), 12);
        $env = NodeEnvironment::empty();

        $this->analyzer->analyze($tuple, $env);
    }

    public function testUnevenBindings(): void
    {
        $this->expectException(AnalyzerException::class);
        $this->expectExceptionMessage('Bindings must be a even number of parameters');

        $tuple = Tuple::create(Symbol::create('let'), Tuple::create(12));
        $env = NodeEnvironment::empty();

        $this->analyzer->analyze($tuple, $env);
    }

    public function testWithNoBindings(): void
    {
        $tuple = Tuple::create(Symbol::create('let'), Tuple::create());
        $env = NodeEnvironment::empty();

        $this->assertEquals(
            new LetNode($env, [], new DoNode($env, [], new LiteralNode($env, null)), false),
            $this->analyzer->analyze($tuple, $env)
        );
    }

    public function testWithOneBinding(): void
    {
        Symbol::resetGen();
        $tuple = Tuple::create(Symbol::create('let'), Tuple::create(Symbol::create('a'), 1));
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
                    $env->withLocals([Symbol::create('a')])->withShadowedLocal(Symbol::create('a'), Symbol::create('a_1')),
                    [],
                    new LiteralNode(
                        $env->withLocals([Symbol::create('a')])->withShadowedLocal(Symbol::create('a'), Symbol::create('a_1')),
                        null
                    )
                ),
                false
            ),
            $this->analyzer->analyze($tuple, $env)
        );
    }

    public function testWithOneBodyExpression(): void
    {
        $tuple = Tuple::create(Symbol::create('let'), Tuple::create(), 1);
        $env = NodeEnvironment::empty();

        $this->assertEquals(
            new LetNode(
                $env,
                [],
                new DoNode(
                    $env,
                    [],
                    new LiteralNode($env, 1)
                ),
                false
            ),
            $this->analyzer->analyze($tuple, $env)
        );
    }

    public function testWithTwoBodyExpression(): void
    {
        $tuple = Tuple::create(Symbol::create('let'), Tuple::create(), 1, 2);
        $env = NodeEnvironment::empty()->withContext(NodeEnvironment::CONTEXT_EXPRESSION);

        $this->assertEquals(
            new LetNode(
                $env,
                [],
                new DoNode(
                    $env->withContext(NodeEnvironment::CONTEXT_RETURN),
                    [new LiteralNode($env->withContext(NodeEnvironment::CONTEXT_STATEMENT)->withDisallowRecurFrame(), 1)],
                    new LiteralNode($env->withContext(NodeEnvironment::CONTEXT_RETURN), 2)
                ),
                false
            ),
            $this->analyzer->analyze($tuple, $env)
        );
    }
}
