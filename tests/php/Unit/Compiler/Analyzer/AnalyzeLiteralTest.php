<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer;

use Phel\Compiler\Analyzer;
use Phel\Compiler\Analyzer\AnalyzeLiteral;
use Phel\Compiler\Analyzer\AnalyzeTable;
use Phel\Compiler\Ast\LiteralNode;
use Phel\Compiler\Ast\TableNode;
use Phel\Compiler\GlobalEnvironment;
use Phel\Compiler\NodeEnvironment;
use Phel\Lang\Symbol;
use Phel\Lang\Table;
use PHPUnit\Framework\TestCase;

final class AnalyzeLiteralTest extends TestCase
{
    private AnalyzeLiteral $literalAnalzyer;

    public function setUp(): void
    {
        $this->literalAnalzyer = new AnalyzeLiteral(new Analyzer(new GlobalEnvironment()));
    }

    public function testSymbolLiteral(): void
    {
        $env = NodeEnvironment::empty();
        self::assertEquals(
            new LiteralNode($env, Symbol::create('test'), null),
            $this->literalAnalzyer->analyze(Symbol::create('test'), $env)
        );
    }

    public function testNumberLiteral(): void
    {
        $env = NodeEnvironment::empty();
        self::assertEquals(
            new LiteralNode($env, 2, null),
            $this->literalAnalzyer->analyze(2, $env)
        );
    }
}
