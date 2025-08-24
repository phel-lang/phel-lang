<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\SpecialForm;

use Phel;
use Phel\Compiler\Application\Analyzer;
use Phel\Compiler\Domain\Analyzer\AnalyzerInterface;
use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Analyzer\Ast\DoNode;
use Phel\Compiler\Domain\Analyzer\Ast\FnNode;
use Phel\Compiler\Domain\Analyzer\Ast\IfNode;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Ast\LocalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\RecurFrame;
use Phel\Compiler\Domain\Analyzer\Ast\RecurNode;
use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironment;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\RecurSymbol;
use Phel\Lang\Symbol;
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

        $list = Phel::list([Symbol::create('unknown')]);
        $env = NodeEnvironment::empty();

        (new RecurSymbol($this->analyzer))->analyze($list, $env);
    }

    public function test_missing_frame(): void
    {
        $this->expectException(AnalyzerException::class);
        $this->expectExceptionMessage("Can't call 'recur here");

        $list = Phel::list([Symbol::create(Symbol::NAME_RECUR)]);
        $env = NodeEnvironment::empty();

        $this->analyzer->analyze($list, $env);
    }

    public function test_wrong_number_of_arguments_for_single_param(): void
    {
        $this->expectException(AnalyzerException::class);
        $this->expectExceptionMessage("Wrong number of arguments for 'recur. Expected: 1 args, got: 0");

        $list = Phel::list([
            Symbol::create(Symbol::NAME_FN),
            Phel::vector([Symbol::create('x')]),
            Phel::list([
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

        $list = Phel::list([
            Symbol::create(Symbol::NAME_FN),
            Phel::vector([Symbol::create('x'), Symbol::create('y')]),
            Phel::list([
                Symbol::create(Symbol::NAME_RECUR),
                Phel::vector([Symbol::create('x')]),
            ]),
        ]);
        $env = NodeEnvironment::empty();

        $this->analyzer->analyze($list, $env);
    }

    public function test_fn_recursion_point(): void
    {
        $list = Phel::list([
            Symbol::create(Symbol::NAME_FN),
            Phel::vector([Symbol::create('x')]),
            Phel::list([
                Symbol::create(Symbol::NAME_IF),
                Phel::list([
                    Symbol::create('php/=='),
                    Symbol::create('x'),
                    0,
                ]),
                Symbol::create('x'),
                Phel::list([
                    Symbol::create(Symbol::NAME_RECUR),
                    Phel::list([
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
