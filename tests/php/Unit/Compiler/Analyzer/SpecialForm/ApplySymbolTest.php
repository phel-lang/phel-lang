<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\SpecialForm;

use Phel\Compiler\Analyzer\Analyzer;
use Phel\Compiler\Analyzer\AnalyzerInterface;
use Phel\Compiler\Analyzer\Ast\ApplyNode;
use Phel\Compiler\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Analyzer\Ast\PhpVarNode;
use Phel\Compiler\Analyzer\Ast\VectorNode;
use Phel\Compiler\Analyzer\Environment\GlobalEnvironment;
use Phel\Compiler\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Analyzer\TypeAnalyzer\SpecialForm\ApplySymbol;
use Phel\Compiler\Exceptions\AbstractLocatedException;
use Phel\Lang\Symbol;
use Phel\Lang\TypeFactory;
use PHPUnit\Framework\TestCase;

final class ApplySymbolTest extends TestCase
{
    private AnalyzerInterface $analyzer;

    public function setUp(): void
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
            ->withContext(NodeEnvironment::CONTEXT_EXPRESSION)
            ->withDisallowRecurFrame()
            ->withDisallowRecurFrame();

        self::assertEquals(
            new ApplyNode(
                NodeEnvironment::empty(),
                new PhpVarNode(NodeEnvironment::empty()->withContext(NodeEnvironment::CONTEXT_EXPRESSION)->withDisallowRecurFrame(), '+'),
                [new VectorNode(
                    NodeEnvironment::empty()->withContext(NodeEnvironment::CONTEXT_EXPRESSION)->withDisallowRecurFrame(),
                    [
                        new LiteralNode($envLiteral, 1),
                        new LiteralNode($envLiteral, 2),
                        new LiteralNode($envLiteral, 3),
                    ]
                )]
            ),
            $applyNode
        );
    }
}
