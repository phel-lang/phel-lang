<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\TupleSymbol;

use Phel\Compiler\Analyzer\Analyzer;
use Phel\Compiler\Analyzer\TypeAnalyzer\TupleSymbol\DefSymbol;
use Phel\Compiler\Analyzer\AnalyzerInterface;
use Phel\Compiler\Ast\LiteralNode;
use Phel\Compiler\Environment\GlobalEnvironment;
use Phel\Compiler\Environment\NodeEnvironment;
use Phel\Exceptions\PhelCodeException;
use Phel\Lang\Symbol;
use Phel\Lang\Table;
use Phel\Lang\Tuple;
use PHPUnit\Framework\TestCase;

final class DefSymbolTest extends TestCase
{
    private AnalyzerInterface $analyzer;

    public function setUp(): void
    {
        $this->analyzer = new Analyzer(new GlobalEnvironment());
    }

    public function testWithDefNotAllowed(): void
    {
        $this->expectException(PhelCodeException::class);
        $this->expectExceptionMessage("'def inside of a 'def is forbidden");

        $env = NodeEnvironment::empty()->withDefAllowed(false);
        (new DefSymbol($this->analyzer))->analyze(Tuple::create(), $env);
    }

    public function testEnsureDefIsNotAllowedInsideADefSymbol(): void
    {
        $this->expectException(PhelCodeException::class);
        $this->expectExceptionMessage("'def inside of a 'def is forbidden");

        $tuple = Tuple::create(Symbol::create(Symbol::NAME_DEF), Symbol::create('1'), 'any value');
        $env = NodeEnvironment::empty();
        $defNode = (new DefSymbol($this->analyzer))->analyze($tuple, $env);
        (new DefSymbol($this->analyzer))->analyze($tuple, $defNode->getInit()->getEnv());
    }

    public function testWithWrongNumberOfArguments(): void
    {
        $this->expectException(PhelCodeException::class);
        $this->expectExceptionMessage("Two or three arguments are required for 'def. Got 2");

        $tuple = Tuple::create(Symbol::create(Symbol::NAME_DEF), Symbol::create('1'));
        (new DefSymbol($this->analyzer))->analyze($tuple, NodeEnvironment::empty());
    }

    public function testFirstArgumentMustBeSymbol(): void
    {
        $this->expectException(PhelCodeException::class);
        $this->expectExceptionMessage("First argument of 'def must be a Symbol.");

        $tuple = Tuple::create(Symbol::create(Symbol::NAME_DEF), 'not a symbol', '2');
        (new DefSymbol($this->analyzer))->analyze($tuple, NodeEnvironment::empty());
    }

    public function testMetaAndInitValues(): void
    {
        $tuple = Tuple::create(Symbol::create(Symbol::NAME_DEF), Symbol::create('1'), 'any value');
        $env = NodeEnvironment::empty();
        $defNode = (new DefSymbol($this->analyzer))->analyze($tuple, $env);

        self::assertEquals(Table::fromKVs(), $defNode->getMeta());

        self::assertEquals(
            (new LiteralNode($env, 'any value'))->getValue(),
            $defNode->getInit()->getValue()
        );
    }
}
