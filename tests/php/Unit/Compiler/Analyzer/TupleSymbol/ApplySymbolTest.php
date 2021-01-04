<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\TupleSymbol;

use Phel\Compiler\Analyzer\Analyzer;
use Phel\Compiler\Analyzer\AnalyzerInterface;
use Phel\Compiler\Analyzer\Ast\ApplyNode;
use Phel\Compiler\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Analyzer\Ast\PhpVarNode;
use Phel\Compiler\Analyzer\Ast\TupleNode;
use Phel\Compiler\Analyzer\Environment\GlobalEnvironment;
use Phel\Compiler\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Analyzer\TypeAnalyzer\TupleSymbol\ApplySymbol;
use Phel\Exceptions\PhelCodeException;
use Phel\Lang\Symbol;
use Phel\Lang\Tuple;
use PHPUnit\Framework\TestCase;

final class ApplySymbolTest extends TestCase
{
    private AnalyzerInterface $analyzer;

    public function setUp(): void
    {
        $this->analyzer = new Analyzer(new GlobalEnvironment());
    }

    public function testLessThan3Arguments(): void
    {
        $this->expectException(PhelCodeException::class);
        $this->expectExceptionMessage("At least three arguments are required for 'apply");

        $tuple = Tuple::create(
            Symbol::create(Symbol::NAME_APPLY),
            Symbol::create('\\')
        );
        (new ApplySymbol($this->analyzer))->analyze($tuple, NodeEnvironment::empty());
    }

    public function testApplyNode(): void
    {
        $tuple = Tuple::create(
            Symbol::create(Symbol::NAME_APPLY),
            Symbol::createForNamespace('php', '+'),
            Tuple::createBracket(1, 2, 3)
        );
        $applyNode = (new ApplySymbol($this->analyzer))->analyze($tuple, NodeEnvironment::empty());

        $envLiteral = NodeEnvironment::empty()
            ->withContext(NodeEnvironment::CONTEXT_EXPRESSION)
            ->withDisallowRecurFrame()
            ->withDisallowRecurFrame();

        self::assertEquals(
            new ApplyNode(
                NodeEnvironment::empty(),
                new PhpVarNode(NodeEnvironment::empty()->withContext(NodeEnvironment::CONTEXT_EXPRESSION)->withDisallowRecurFrame(), '+'),
                [new TupleNode(
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
