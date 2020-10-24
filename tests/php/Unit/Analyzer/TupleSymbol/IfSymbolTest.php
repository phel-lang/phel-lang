<?php

declare(strict_types=1);

namespace PhelTest\Unit\Analyzer\TupleSymbol;

use Generator;
use Phel\Analyzer;
use Phel\Analyzer\TupleSymbol\IfSymbol;
use Phel\Ast\IfNode;
use Phel\Exceptions\PhelCodeException;
use Phel\GlobalEnvironment;
use Phel\Lang\Symbol;
use Phel\Lang\Tuple;
use Phel\NodeEnvironment;
use PHPUnit\Framework\TestCase;

final class IfSymbolTest extends TestCase
{
    /**
     * @dataProvider providerRequiresAtLeastTwoOrThreeArgs
     */
    public function testRequiresAtLeastTwoOrThreeArgs(Tuple $tuple): void
    {
        $this->expectException(PhelCodeException::class);
        $this->expectExceptionMessage("'if requires two or three arguments");

        $this->analyze($tuple);
    }

    public function providerRequiresAtLeastTwoOrThreeArgs(): Generator
    {
        yield 'No arguments provided: (if)' => [
            'wrongTuple' => Tuple::create(
                Symbol::create(Symbol::NAME_IF)
            ),
        ];

        yield 'Only one argument provided: (if one)' => [
            'wrongTuple' => Tuple::create(
                Symbol::create(Symbol::NAME_IF),
                Symbol::create('one'),
            ),
        ];

        yield 'Only one argument provided: (if one two three four)' => [
            'wrongTuple' => Tuple::create(
                Symbol::create(Symbol::NAME_IF),
                Symbol::create('one'),
                Symbol::create('two'),
                Symbol::create('three'),
                Symbol::create('four'),
            ),
        ];
    }

    private function analyze(Tuple $tuple): IfNode
    {
        $analyzer = new Analyzer(new GlobalEnvironment());

        return (new IfSymbol($analyzer))->analyze($tuple, NodeEnvironment::empty());
    }
}
