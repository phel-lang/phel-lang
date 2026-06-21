<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer;

use Phel\Compiler\Application\Analyzer;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironment;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\AnalyzeLiteral;
use Phel\Lang\Symbol;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class AnalyzeLiteralTest extends TestCase
{
    private AnalyzeLiteral $literalAnalyzer;

    protected function setUp(): void
    {
        $this->literalAnalyzer = new AnalyzeLiteral();
    }

    public function test_symbol_literal(): void
    {
        $env = NodeEnvironment::empty();
        self::assertEquals(
            new LiteralNode($env, Symbol::create('test')),
            $this->literalAnalyzer->analyze(Symbol::create('test'), $env),
        );
    }

    public function test_number_literal(): void
    {
        $env = NodeEnvironment::empty();
        self::assertEquals(
            new LiteralNode($env, 2),
            $this->literalAnalyzer->analyze(2, $env),
        );
    }

    public function test_array_literal(): void
    {
        $env = NodeEnvironment::empty();
        self::assertEquals(
            new LiteralNode($env, [1, 2]),
            $this->literalAnalyzer->analyze([1, 2], $env),
        );
    }

    public function test_analyzer_reuses_a_single_literal_analyzer_instance(): void
    {
        $analyzer = new Analyzer(new GlobalEnvironment());
        $env = NodeEnvironment::empty();

        $first = $analyzer->analyze(1, $env);
        $reused = $this->literalAnalyzerOf($analyzer);
        $second = $analyzer->analyze('two', $env);

        self::assertSame($reused, $this->literalAnalyzerOf($analyzer));
        self::assertEquals(new LiteralNode($env, 1), $first);
        self::assertEquals(new LiteralNode($env, 'two'), $second);
    }

    private function literalAnalyzerOf(Analyzer $analyzer): AnalyzeLiteral
    {
        $property = new ReflectionProperty(Analyzer::class, 'literalAnalyzer');
        $value = $property->getValue($analyzer);
        self::assertInstanceOf(AnalyzeLiteral::class, $value);

        return $value;
    }
}
