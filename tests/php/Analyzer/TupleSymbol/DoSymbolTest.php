<?php

declare(strict_types=1);

namespace PhelTest\Analyzer\TupleSymbol;

use Phel\Analyzer;
use Phel\Analyzer\TupleSymbol\DoSymbol;
use Phel\Ast\DoNode;
use Phel\GlobalEnvironment;
use Phel\Lang\Tuple;
use Phel\NodeEnvironment;
use PHPUnit\Framework\TestCase;

final class DoSymbolTest extends TestCase
{
    private Analyzer $analyzer;

    public function setUp(): void
    {
        $this->analyzer = new Analyzer(new GlobalEnvironment());
    }

    public function testEmptyTuple(): void
    {
        $env = NodeEnvironment::empty();
        $tuple = Tuple::create();

        $expected = new DoNode(
            $env,
            $stmts = [],
            $this->analyzer->analyze(null, $env),
            $tuple->getStartLocation()
        );

        $doNode = (new DoSymbol($this->analyzer))->analyze($tuple, $env);
        self::assertEquals($expected, $doNode);
    }
}
