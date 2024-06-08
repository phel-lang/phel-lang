<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\SpecialForm;

use Phel\Compiler\Application\Analyzer;
use Phel\Compiler\Domain\Analyzer\AnalyzerInterface;
use Phel\Compiler\Domain\Analyzer\Ast\BindingNode;
use Phel\Compiler\Domain\Analyzer\Ast\DoNode;
use Phel\Compiler\Domain\Analyzer\Ast\LetNode;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironment;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\Binding\DeconstructorInterface;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\LetSymbol;
use Phel\Lang\Symbol;
use Phel\Lang\TypeFactory;
use PHPUnit\Framework\TestCase;

final class LetSymbolTest extends TestCase
{
    private AnalyzerInterface $analyzer;

    protected function setUp(): void
    {
        $this->analyzer = new Analyzer(new GlobalEnvironment());
    }

    public function test_wrong_symbol_name(): void
    {
        $this->expectException(AnalyzerException::class);
        $this->expectExceptionMessage("This is not a 'let.");

        $list = TypeFactory::getInstance()->persistentListFromArray([Symbol::create('unknown')]);
        $env = NodeEnvironment::empty();

        $analyzer = new LetSymbol($this->analyzer, $this->createMock(DeconstructorInterface::class));

        $analyzer->analyze($list, $env);
    }

    public function test_wrong_arguments(): void
    {
        $this->expectException(AnalyzerException::class);
        $this->expectExceptionMessage("At least two arguments are required for 'let");

        $list = TypeFactory::getInstance()->persistentListFromArray([Symbol::create('let')]);
        $env = NodeEnvironment::empty();

        $this->analyzer->analyze($list, $env);
    }

    public function test_wrong_second_argument(): void
    {
        $this->expectException(AnalyzerException::class);
        $this->expectExceptionMessage('Binding parameter must be a vector');

        $list = TypeFactory::getInstance()->persistentListFromArray([Symbol::create('let'), 12]);
        $env = NodeEnvironment::empty();

        $this->analyzer->analyze($list, $env);
    }

    public function test_uneven_bindings(): void
    {
        $this->expectException(AnalyzerException::class);
        $this->expectExceptionMessage('Bindings must be a even number of parameters');

        $list = TypeFactory::getInstance()->persistentListFromArray([
            Symbol::create('let'),
            TypeFactory::getInstance()->persistentVectorFromArray([12]),
        ]);
        $env = NodeEnvironment::empty();

        $this->analyzer->analyze($list, $env);
    }

    public function test_with_no_bindings(): void
    {
        $list = TypeFactory::getInstance()->persistentListFromArray([
            Symbol::create('let'),
            TypeFactory::getInstance()->persistentVectorFromArray([]),
        ]);
        $env = NodeEnvironment::empty();

        $this->assertEquals(
            new LetNode($env, [], new DoNode($env, [], new LiteralNode($env, null)), false),
            $this->analyzer->analyze($list, $env),
        );
    }

    public function test_with_one_binding(): void
    {
        Symbol::resetGen();
        $list = TypeFactory::getInstance()->persistentListFromArray([
            Symbol::create('let'),
            TypeFactory::getInstance()->persistentVectorFromArray([
                Symbol::create('a'), 1,
            ]),
        ]);
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
                            $env->withExpressionContext()->withDisallowRecurFrame()->withDisallowRecurFrame()->withBoundTo('.a'),
                            1,
                        ),
                    ),
                ],
                new DoNode(
                    $env->withLocals([Symbol::create('a')])->withShadowedLocal(Symbol::create('a'), Symbol::create('a_1')),
                    [],
                    new LiteralNode(
                        $env->withLocals([Symbol::create('a')])->withShadowedLocal(Symbol::create('a'), Symbol::create('a_1')),
                        null,
                    ),
                ),
                false,
            ),
            $this->analyzer->analyze($list, $env),
        );
    }

    public function test_with_one_body_expression(): void
    {
        $list = TypeFactory::getInstance()->persistentListFromArray([
            Symbol::create('let'),
            TypeFactory::getInstance()->persistentVectorFromArray([]),
            1,
        ]);
        $env = NodeEnvironment::empty();

        $this->assertEquals(
            new LetNode(
                $env,
                [],
                new DoNode(
                    $env,
                    [],
                    new LiteralNode($env, 1),
                ),
                false,
            ),
            $this->analyzer->analyze($list, $env),
        );
    }

    public function test_with_two_body_expression(): void
    {
        $list = TypeFactory::getInstance()->persistentListFromArray([
            Symbol::create('let'),
            TypeFactory::getInstance()->persistentVectorFromArray([]),
            1,
            2,
        ]);
        $env = NodeEnvironment::empty()->withExpressionContext();

        $this->assertEquals(
            new LetNode(
                $env,
                [],
                new DoNode(
                    $env->withReturnContext(),
                    [new LiteralNode($env->withStatementContext()->withDisallowRecurFrame(), 1)],
                    new LiteralNode($env->withReturnContext(), 2),
                ),
                false,
            ),
            $this->analyzer->analyze($list, $env),
        );
    }
}
