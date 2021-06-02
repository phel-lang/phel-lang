<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer;

use Phel\Compiler\Analyzer\Analyzer;
use Phel\Compiler\Analyzer\Ast\ArrayNode;
use Phel\Compiler\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Analyzer\Environment\GlobalEnvironment;
use Phel\Compiler\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Analyzer\TypeAnalyzer\AnalyzeArray;
use Phel\Lang\PhelArray;
use PHPUnit\Framework\TestCase;

final class AnalyzeArrayTest extends TestCase
{
    private AnalyzeArray $arrayAnalzyer;

    public function setUp(): void
    {
        $this->arrayAnalzyer = new AnalyzeArray(new Analyzer(new GlobalEnvironment()));
    }

    public function test_empty_array(): void
    {
        $env = NodeEnvironment::empty();
        self::assertEquals(
            new ArrayNode($env, [], null),
            $this->arrayAnalzyer->analyze(PhelArray::create(), $env)
        );
    }

    public function test_array(): void
    {
        $env = NodeEnvironment::empty();
        self::assertEquals(
            new ArrayNode($env, [
                new LiteralNode($env->withContext(NodeEnvironment::CONTEXT_EXPRESSION), 1, null),
            ], null),
            $this->arrayAnalzyer->analyze(PhelArray::create(1), $env)
        );
    }
}
