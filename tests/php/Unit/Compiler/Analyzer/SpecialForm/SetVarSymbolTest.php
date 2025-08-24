<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\SpecialForm;

use Phel;
use Phel\Compiler\Application\Analyzer;
use Phel\Compiler\Domain\Analyzer\AnalyzerInterface;
use Phel\Compiler\Domain\Analyzer\Ast\SetVarNode;
use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironment;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\SetVarSymbol;
use Phel\Lang\Symbol;
use PHPUnit\Framework\TestCase;

final class SetVarSymbolTest extends TestCase
{
    private AnalyzerInterface $analyzer;

    protected function setUp(): void
    {
        $this->analyzer = new Analyzer(new GlobalEnvironment());
    }

    public function test_first_argument_must_be_symbol(): void
    {
        $this->expectException(AnalyzerException::class);
        $this->expectExceptionMessage("First argument of 'def must be a Symbol.");

        $list = Phel::list([
            Symbol::create(Symbol::NAME_SET_VAR),
            'not-a-symbol',
            1,
        ]);

        (new SetVarSymbol($this->analyzer))->analyze($list, NodeEnvironment::empty());
    }

    public function test_set_var_node(): void
    {
        $globalEnv = new GlobalEnvironment();
        $globalEnv->addDefinition('user', Symbol::create('x'));

        $analyzer = new Analyzer($globalEnv);

        $list = Phel::list([
            Symbol::create(Symbol::NAME_SET_VAR),
            Symbol::create('x'),
            2,
        ]);
        $env = NodeEnvironment::empty();

        $expected = new SetVarNode(
            $env,
            $analyzer->analyze(Symbol::create('x'), $env->withExpressionContext()),
            $analyzer->analyze(2, $env->withExpressionContext()),
            $list->getStartLocation(),
        );

        $actual = (new SetVarSymbol($analyzer))->analyze($list, $env);

        self::assertEquals($expected, $actual);
    }
}
