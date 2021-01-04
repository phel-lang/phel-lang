<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer;

use Phel\Compiler\Analyzer\Analyzer;
use Phel\Compiler\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Analyzer\Environment\GlobalEnvironment;
use Phel\Compiler\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Analyzer\TypeAnalyzer\AnalyzeLiteral;
use Phel\Lang\Symbol;
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
