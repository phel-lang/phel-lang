<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\TupleSymbol;

use Phel\Compiler\Analyzer;
use Phel\Compiler\Analyzer\TupleSymbol\ForeachSymbol;
use Phel\Compiler\Ast\ForeachNode;
use Phel\Compiler\NodeEnvironment;
use Phel\Exceptions\PhelCodeException;
use Phel\Compiler\GlobalEnvironment;
use Phel\Lang\Symbol;
use Phel\Lang\Table;
use Phel\Lang\Tuple;
use PHPUnit\Framework\TestCase;

final class ForeachSymbolTest extends TestCase
{
    public function testRequiresAtLeastTwoArg(): void
    {
        $this->expectException(PhelCodeException::class);
        $this->expectExceptionMessage("At least two arguments are required for 'foreach");

        // (foreach)
        $tuple = Tuple::create(
            Symbol::create(Symbol::NAME_FOREACH)
        );

        $this->analyze($tuple);
    }

    public function testFirstArgMustBeATuple(): void
    {
        $this->expectException(PhelCodeException::class);
        $this->expectExceptionMessage("First argument of 'foreach must be a tuple.");

        // (foreach x)
        $mainTuple = Tuple::create(
            Symbol::create(Symbol::NAME_FOREACH),
            Symbol::create('x')
        );

        $this->analyze($mainTuple);
    }

    public function testArgForTupleCanNotBe1(): void
    {
        $this->expectException(PhelCodeException::class);
        $this->expectExceptionMessage("Tuple of 'foreach must have exactly two or three elements.");

        // (foreach [x])
        $mainTuple = Tuple::create(
            Symbol::create(Symbol::NAME_FOREACH),
            Tuple::createBracket(
                Symbol::create('x')
            )
        );

        $this->analyze($mainTuple);
    }

    public function testValueSymbolFromTupleWith2Args(): void
    {
        // (foreach [x []])
        $mainTuple = Tuple::create(
            Symbol::create(Symbol::NAME_FOREACH),
            Tuple::createBracket(
                Symbol::create('x'),
                Tuple::createBracket()
            ),
            Tuple::create()
        );

        $node = $this->analyze($mainTuple);
        self::assertEquals(Symbol::create('x'), $node->getValueSymbol());
    }

    public function testValueSymbolFromTupleWith3Args(): void
    {
        // (foreach [key value @{}])
        $mainTuple = Tuple::create(
            Symbol::create(Symbol::NAME_FOREACH),
            Tuple::createBracket(
                Symbol::create('key'),
                Symbol::create('value'),
                Table::empty()
            ),
            Tuple::create()
        );

        $node = $this->analyze($mainTuple);
        self::assertEquals(Symbol::create('value'), $node->getValueSymbol());
    }

    public function testArgForTupleCanNotBe4(): void
    {
        $this->expectException(PhelCodeException::class);
        $this->expectExceptionMessage("Tuple of 'foreach must have exactly two or three elements.");

        // (foreach [x y z @{}])
        $mainTuple = Tuple::create(
            Symbol::create(Symbol::NAME_FOREACH),
            Tuple::create(
                Symbol::create('x'),
                Symbol::create('y'),
                Symbol::create('z'),
                Table::empty()
            )
        );

        $this->analyze($mainTuple);
    }

    private function analyze(Tuple $tuple): ForeachNode
    {
        $analyzer = new Analyzer(new GlobalEnvironment());

        return (new ForeachSymbol($analyzer))->analyze($tuple, NodeEnvironment::empty());
    }
}
