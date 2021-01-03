<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer;

use Phel\Compiler\Analyzer;
use Phel\Compiler\Analyzer\AnalyzeBracketTuple;
use Phel\Compiler\Ast\LiteralNode;
use Phel\Compiler\Ast\TupleNode;
use Phel\Compiler\GlobalEnvironment;
use Phel\Compiler\NodeEnvironment;
use Phel\Lang\Tuple;
use PHPUnit\Framework\TestCase;

final class AnalyzeBracketTupleTest extends TestCase
{
    private AnalyzeBracketTuple $bracketTupleAnalzyer;

    public function setUp(): void
    {
        $this->bracketTupleAnalzyer = new AnalyzeBracketTuple(new Analyzer(new GlobalEnvironment()));
    }

    public function testEmptyTuple(): void
    {
        $env = NodeEnvironment::empty();
        self::assertEquals(
            new TupleNode($env, [], null),
            $this->bracketTupleAnalzyer->analyze(Tuple::createBracket(), $env)
        );
    }

    public function testTuple(): void
    {
        $env = NodeEnvironment::empty();
        self::assertEquals(
            new TupleNode($env, [
                new LiteralNode($env->withDisallowRecurFrame()->withContext(NodeEnvironment::CONTEXT_EXPRESSION), 1, null),
            ], null),
            $this->bracketTupleAnalzyer->analyze(Tuple::createBracket(1), $env)
        );
    }
}
