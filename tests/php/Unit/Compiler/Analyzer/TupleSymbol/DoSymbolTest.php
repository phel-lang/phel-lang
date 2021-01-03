<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\TupleSymbol;

use Phel\Compiler\Analyzer\Analyzer;
use Phel\Compiler\Analyzer\TypeAnalyzer\TupleSymbol\DoSymbol;
use Phel\Compiler\Analyzer\AnalyzerInterface;
use Phel\Compiler\Analyzer\Ast\DoNode;
use Phel\Compiler\Analyzer\Environment\NodeEnvironment;
use Phel\Exceptions\PhelCodeException;
use Phel\Compiler\Analyzer\Environment\GlobalEnvironment;
use Phel\Lang\Symbol;
use Phel\Lang\Tuple;
use PHPUnit\Framework\TestCase;

final class DoSymbolTest extends TestCase
{
    private AnalyzerInterface $analyzer;

    public function setUp(): void
    {
        $this->analyzer = new Analyzer(new GlobalEnvironment());
    }

    public function testWrongSymbolName(): void
    {
        $this->expectException(PhelCodeException::class);
        $this->expectExceptionMessage("This is not a 'do.");

        $tuple = Tuple::create(Symbol::create('unknown'));
        $env = NodeEnvironment::empty();
        (new DoSymbol($this->analyzer))->analyze($tuple, $env);
    }

    public function testEmptyTuple(): void
    {
        $env = NodeEnvironment::empty();
        $tuple = Tuple::create(
            Symbol::create(Symbol::NAME_DO)
        );

        $expected = new DoNode(
            $env,
            $stmts = [],
            $this->analyzer->analyze(null, $env),
            $tuple->getStartLocation()
        );

        $actual = (new DoSymbol($this->analyzer))->analyze($tuple, $env);
        self::assertEquals($expected, $actual);
    }

    public function testTupleWithinAnotherTuple(): void
    {
        $env = NodeEnvironment::empty();

        $tuple = Tuple::create(
            Symbol::create(Symbol::NAME_DO),
            Tuple::create(Symbol::create(Symbol::NAME_IF), true, true)
        );

        $expected = new DoNode(
            $env,
            $stmts = [],
            $this->analyzer->analyze($tuple[count($tuple) - 1], $env),
            $tuple->getStartLocation()
        );

        $actual = (new DoSymbol($this->analyzer))->analyze($tuple, $env);
        self::assertEquals($expected, $actual);
    }
}
