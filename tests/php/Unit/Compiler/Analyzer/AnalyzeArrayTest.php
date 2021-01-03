<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer;

use Phel\Compiler\Analyzer;
use Phel\Compiler\Analyzer\AnalyzeArray;
use Phel\Compiler\Analyzer\AnalyzeBracketTuple;
use Phel\Compiler\Analyzer\AnalyzeLiteral;
use Phel\Compiler\Analyzer\AnalyzeTable;
use Phel\Compiler\Ast\ArrayNode;
use Phel\Compiler\Ast\LiteralNode;
use Phel\Compiler\Ast\TableNode;
use Phel\Compiler\Ast\TupleNode;
use Phel\Compiler\GlobalEnvironment;
use Phel\Compiler\NodeEnvironment;
use Phel\Lang\PhelArray;
use Phel\Lang\Symbol;
use Phel\Lang\Table;
use Phel\Lang\Tuple;
use PHPUnit\Framework\TestCase;

final class AnalyzeArrayTest extends TestCase
{
    private AnalyzeArray $arrayAnalzyer;

    public function setUp(): void
    {
        $this->arrayAnalzyer = new AnalyzeArray(new Analyzer(new GlobalEnvironment()));
    }

    public function testEmptyArray(): void
    {
        $env = NodeEnvironment::empty();
        self::assertEquals(
            new ArrayNode($env, [], null),
            $this->arrayAnalzyer->analyze(PhelArray::create(), $env)
        );
    }

    public function testArray(): void
    {
        $env = NodeEnvironment::empty();
        self::assertEquals(
            new ArrayNode($env, [
                new LiteralNode($env->withContext(NodeEnvironment::CONTEXT_EXPRESSION), 1, null)
            ], null),
            $this->arrayAnalzyer->analyze(PhelArray::create(1), $env)
        );
    }
}
