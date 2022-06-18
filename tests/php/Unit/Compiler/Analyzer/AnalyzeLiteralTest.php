<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer;

use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\AnalyzeLiteral;
use Phel\Lang\Symbol;
use PHPUnit\Framework\TestCase;

final class AnalyzeLiteralTest extends TestCase
{
    private AnalyzeLiteral $literalAnalyzer;

    public function setUp(): void
    {
        $this->literalAnalyzer = new AnalyzeLiteral();
    }

    public function test_symbol_literal(): void
    {
        $env = NodeEnvironment::empty();
        self::assertEquals(
            new LiteralNode($env, Symbol::create('test'), null),
            $this->literalAnalyzer->analyze(Symbol::create('test'), $env)
        );
    }

    public function test_number_literal(): void
    {
        $env = NodeEnvironment::empty();
        self::assertEquals(
            new LiteralNode($env, 2, null),
            $this->literalAnalyzer->analyze(2, $env)
        );
    }

    public function test_array_literal(): void
    {
        $env = NodeEnvironment::empty();
        self::assertEquals(
            new LiteralNode($env, [1, 2], null),
            $this->literalAnalyzer->analyze([1, 2], $env)
        );
    }
}
