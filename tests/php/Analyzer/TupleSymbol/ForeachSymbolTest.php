<?php

declare(strict_types=1);

namespace PhelTest\Analyzer\TupleSymbol;

use Generator;
use Phel\Analyzer;
use Phel\Analyzer\TupleSymbol\ForeachSymbol;
use Phel\Ast\ForeachNode;
use Phel\Exceptions\PhelCodeException;
use Phel\GlobalEnvironment;
use Phel\Lang\Symbol;
use Phel\Lang\Tuple;
use Phel\NodeEnvironment;
use PHPUnit\Framework\TestCase;

final class ForeachSymbolTest extends TestCase
{
    private Analyzer $analyzer;

    public function setUp(): void
    {
        $this->analyzer = new Analyzer(new GlobalEnvironment());
    }

    public function testRequiresAtLeastTwoArg(): void
    {
        $this->expectException(PhelCodeException::class);
        $this->expectExceptionMessage("At least two arguments are required for 'foreach");

        // This is the same as: (foreach)
        $tuple = Tuple::create(
            Symbol::create(Symbol::NAME_FOREACH)
        );

        $this->analyze($tuple);
    }

    public function testFirstArgMustBeATuple(): void
    {
        $this->expectException(PhelCodeException::class);
        $this->expectExceptionMessage("First argument of 'foreach must be a tuple.");

        // This is the same as: (foreach anything)
        $mainTuple = Tuple::create(
            Symbol::create(Symbol::NAME_FOREACH),
            Symbol::create('anything')
        );

        $this->analyze($mainTuple);
    }

    /** @dataProvider providerNumberOfTupleArgs */
    public function testNumberOfTupleArgs(Tuple $mainTuple, bool $error): void
    {
        if ($error) {
            $this->expectException(PhelCodeException::class);
            $this->expectExceptionMessage("Tuple of 'foreach must have exactly two or three elements.");
        } else {
            self::assertTrue(true); // In order to have an assertion without an error
        }

        $this->analyze($mainTuple);
    }

    public function providerNumberOfTupleArgs(): Generator
    {
        yield '(foreach (x) x)' => [
            'mainTuple' => Tuple::create(
                Symbol::create(Symbol::NAME_FOREACH),
                Tuple::create(
                    Symbol::create('x')
                )
            ),
            'error' => true,
        ];

        yield '(foreach (()))' => [
            'mainTuple' => Tuple::create(
                Symbol::create(Symbol::NAME_FOREACH),
                Tuple::create(
                    Symbol::create(''),
                    Tuple::create()
                ),
                Tuple::create()
            ),
            'error' => false,
        ];

        yield  '(foreach (w x y z))' => [
            'mainTuple' => Tuple::create(
                Symbol::create(Symbol::NAME_FOREACH),
                Tuple::create(
                    Symbol::create('w'),
                    Symbol::create('x'),
                    Symbol::create('y'),
                    Symbol::create('z'),
                )
            ),
            'error' => true,
        ];
    }

    private function analyze(Tuple $tuple): ForeachNode
    {
        return (new ForeachSymbol($this->analyzer))->analyze($tuple, NodeEnvironment::empty());
    }
}
