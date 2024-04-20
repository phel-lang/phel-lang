<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\SpecialForm;

use Phel\Lang\Symbol;
use Phel\Lang\TypeFactory;
use Phel\Transpiler\Domain\Analyzer\Analyzer;
use Phel\Transpiler\Domain\Analyzer\AnalyzerInterface;
use Phel\Transpiler\Domain\Analyzer\Ast\CallNode;
use Phel\Transpiler\Domain\Analyzer\Ast\DoNode;
use Phel\Transpiler\Domain\Analyzer\Ast\FnNode;
use Phel\Transpiler\Domain\Analyzer\Ast\IfNode;
use Phel\Transpiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Transpiler\Domain\Analyzer\Ast\LocalVarNode;
use Phel\Transpiler\Domain\Analyzer\Ast\PhpVarNode;
use Phel\Transpiler\Domain\Analyzer\Ast\RecurFrame;
use Phel\Transpiler\Domain\Analyzer\Ast\RecurNode;
use Phel\Transpiler\Domain\Analyzer\Environment\GlobalEnvironment;
use Phel\Transpiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Transpiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Transpiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\RecurSymbol;
use PHPUnit\Framework\TestCase;

final class RecurSymbolTest extends TestCase
{
    private AnalyzerInterface $analyzer;

    protected function setUp(): void
    {
        $this->analyzer = new Analyzer(new GlobalEnvironment());
    }

    public function test_wrong_symbol_name(): void
    {
        $this->expectException(AnalyzerException::class);
        $this->expectExceptionMessage("This is not a 'recur.");

        $list = TypeFactory::getInstance()->persistentListFromArray([Symbol::create('unknown')]);
        $env = NodeEnvironment::empty();

        (new RecurSymbol($this->analyzer))->analyze($list, $env);
    }

    public function test_missing_frame(): void
    {
        $this->expectException(AnalyzerException::class);
        $this->expectExceptionMessage("Can't call 'recur here");

        $list = TypeFactory::getInstance()->persistentListFromArray([Symbol::create(Symbol::NAME_RECUR)]);
        $env = NodeEnvironment::empty();

        $this->analyzer->analyze($list, $env);
    }

    public function test_wrong_number_of_arguments_for_single_param(): void
    {
        $this->expectException(AnalyzerException::class);
        $this->expectExceptionMessage("Wrong number of arguments for 'recur. Expected: 1 args, got: 0");

        $list = TypeFactory::getInstance()->persistentListFromArray([
            Symbol::create(Symbol::NAME_FN),
            TypeFactory::getInstance()->persistentVectorFromArray([Symbol::create('x')]),
            TypeFactory::getInstance()->persistentListFromArray([
                Symbol::create(Symbol::NAME_RECUR),
            ]),
        ]);
        $env = NodeEnvironment::empty();

        $this->analyzer->analyze($list, $env);
    }

    public function test_wrong_number_of_arguments_for_two_params(): void
    {
        $this->expectException(AnalyzerException::class);
        $this->expectExceptionMessage("Wrong number of arguments for 'recur. Expected: 2 args, got: 1");

        $list = TypeFactory::getInstance()->persistentListFromArray([
            Symbol::create(Symbol::NAME_FN),
            TypeFactory::getInstance()->persistentVectorFromArray([Symbol::create('x'), Symbol::create('y')]),
            TypeFactory::getInstance()->persistentListFromArray([
                Symbol::create(Symbol::NAME_RECUR),
                TypeFactory::getInstance()->persistentVectorFromArray([Symbol::create('x')]),
            ]),
        ]);
        $env = NodeEnvironment::empty();

        $this->analyzer->analyze($list, $env);
    }

    public function test_fn_recursion_point(): void
    {
        $list = TypeFactory::getInstance()->persistentListFromArray([
            Symbol::create(Symbol::NAME_FN),
            TypeFactory::getInstance()->persistentVectorFromArray([Symbol::create('x')]),
            TypeFactory::getInstance()->persistentListFromArray([
                Symbol::create(Symbol::NAME_IF),
                TypeFactory::getInstance()->persistentListFromArray([
                    Symbol::create('php/=='),
                    Symbol::create('x'),
                    0,
                ]),
                Symbol::create('x'),
                TypeFactory::getInstance()->persistentListFromArray([
                    Symbol::create(Symbol::NAME_RECUR),
                    TypeFactory::getInstance()->persistentListFromArray([
                        Symbol::create('php/-'),
                        Symbol::create('x'),
                        1,
                    ]),
                ]),
            ]),
        ]);
        $env = NodeEnvironment::empty();

        $activeRecurFrame = new RecurFrame([Symbol::create('x')]);
        $activeRecurFrame->setIsActive(true);

        $fnEnv = $env->withLocals([Symbol::create('x')])->withAddedRecurFrame($activeRecurFrame);

        $this->assertEquals(
            new FnNode(
                $env,
                [Symbol::create('x')],
                new DoNode(
                    $fnEnv->withReturnContext(),
                    [],
                    new IfNode(
                        $fnEnv->withReturnContext(),
                        new CallNode(
                            $fnEnv->withExpressionContext()->withDisallowRecurFrame(),
                            new PhpVarNode(
                                $fnEnv->withExpressionContext()->withDisallowRecurFrame()->withDisallowRecurFrame(),
                                '==',
                            ),
                            [
                                new LocalVarNode(
                                    $fnEnv->withExpressionContext()->withDisallowRecurFrame()->withDisallowRecurFrame(),
                                    Symbol::create('x'),
                                ),
                                new LiteralNode(
                                    $fnEnv->withExpressionContext()->withDisallowRecurFrame()->withDisallowRecurFrame(),
                                    0,
                                ),
                            ],
                        ),
                        new LocalVarNode($fnEnv->withReturnContext(), Symbol::create('x')),
                        new RecurNode(
                            $fnEnv->withReturnContext(),
                            $activeRecurFrame,
                            [
                                new CallNode(
                                    $fnEnv->withExpressionContext()->withDisallowRecurFrame(),
                                    new PhpVarNode(
                                        $fnEnv->withExpressionContext()->withDisallowRecurFrame()->withDisallowRecurFrame(),
                                        '-',
                                    ),
                                    [
                                        new LocalVarNode(
                                            $fnEnv->withExpressionContext()->withDisallowRecurFrame()->withDisallowRecurFrame(),
                                            Symbol::create('x'),
                                        ),
                                        new LiteralNode(
                                            $fnEnv->withExpressionContext()->withDisallowRecurFrame()->withDisallowRecurFrame(),
                                            1,
                                        ),
                                    ],
                                ),
                            ],
                        ),
                    ),
                ),
                [],
                false,
                true,
            ),
            $this->analyzer->analyze($list, $env),
        );
    }
}
