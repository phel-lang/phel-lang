<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer;

use Phel\Compiler\Analyzer\Analyzer;
use Phel\Compiler\Analyzer\TypeAnalyzer\AnalyzeArray;
use Phel\Compiler\Ast\ArrayNode;
use Phel\Compiler\Ast\LiteralNode;
use Phel\Compiler\Environment\GlobalEnvironment;
use Phel\Compiler\Environment\NodeEnvironment;
use Phel\Lang\PhelArray;
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
                new LiteralNode($env->withContext(NodeEnvironment::CONTEXT_EXPRESSION), 1, null),
            ], null),
            $this->arrayAnalzyer->analyze(PhelArray::create(1), $env)
        );
    }
}
