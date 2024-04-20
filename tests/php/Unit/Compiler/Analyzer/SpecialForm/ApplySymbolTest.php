<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\SpecialForm;

use Phel\Lang\Symbol;
use Phel\Lang\TypeFactory;
use Phel\Transpiler\Domain\Analyzer\Analyzer;
use Phel\Transpiler\Domain\Analyzer\AnalyzerInterface;
use Phel\Transpiler\Domain\Analyzer\Ast\ApplyNode;
use Phel\Transpiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Transpiler\Domain\Analyzer\Ast\PhpVarNode;
use Phel\Transpiler\Domain\Analyzer\Ast\VectorNode;
use Phel\Transpiler\Domain\Analyzer\Environment\GlobalEnvironment;
use Phel\Transpiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Transpiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\ApplySymbol;
use Phel\Transpiler\Domain\Exceptions\AbstractLocatedException;
use PHPUnit\Framework\TestCase;

final class ApplySymbolTest extends TestCase
{
    private AnalyzerInterface $analyzer;

    protected function setUp(): void
    {
        $this->analyzer = new Analyzer(new GlobalEnvironment());
    }

    public function test_less_than3_arguments(): void
    {
        $this->expectException(AbstractLocatedException::class);
        $this->expectExceptionMessage("At least three arguments are required for 'apply");

        $list = TypeFactory::getInstance()->persistentListFromArray([
            Symbol::create(Symbol::NAME_APPLY),
            Symbol::create('\\'),
        ]);
        (new ApplySymbol($this->analyzer))->analyze($list, NodeEnvironment::empty());
    }

    public function test_apply_node(): void
    {
        $list = TypeFactory::getInstance()->persistentListFromArray([
            Symbol::create(Symbol::NAME_APPLY),
            Symbol::createForNamespace('php', '+'),
            TypeFactory::getInstance()->persistentVectorFromArray([1, 2, 3]),
        ]);
        $applyNode = (new ApplySymbol($this->analyzer))->analyze($list, NodeEnvironment::empty());

        $envLiteral = NodeEnvironment::empty()
            ->withExpressionContext()
            ->withDisallowRecurFrame()
            ->withDisallowRecurFrame();

        self::assertEquals(
            new ApplyNode(
                NodeEnvironment::empty(),
                new PhpVarNode(NodeEnvironment::empty()->withExpressionContext()->withDisallowRecurFrame(), '+'),
                [new VectorNode(
                    NodeEnvironment::empty()->withExpressionContext()->withDisallowRecurFrame(),
                    [
                        new LiteralNode($envLiteral, 1),
                        new LiteralNode($envLiteral, 2),
                        new LiteralNode($envLiteral, 3),
                    ],
                )],
            ),
            $applyNode,
        );
    }
}
