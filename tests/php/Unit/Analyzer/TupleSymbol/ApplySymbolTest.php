<?php

declare(strict_types=1);

namespace PhelTest\Unit\Analyzer\TupleSymbol;

use Phel\Compiler\Analyzer;
use Phel\Compiler\Analyzer\TupleSymbol\ApplySymbol;
use Phel\Compiler\AnalyzerInterface;
use Phel\Compiler\Ast\CallNode;
use Phel\Exceptions\PhelCodeException;
use Phel\Compiler\GlobalEnvironment;
use Phel\Lang\Symbol;
use Phel\Lang\Tuple;
use Phel\Compiler\NodeEnvironment;
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
            '+',
            Tuple::create('1', '2', '3')
        );
        $applyNode = (new ApplySymbol($this->analyzer))->analyze($tuple, NodeEnvironment::empty());

        self::assertSame('+', ($applyNode->getFn())->getValue());
        self::assertInstanceOf(CallNode::class, $applyNode->getArguments()[0]);
    }
}
