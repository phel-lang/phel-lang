<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer;

use Phel\Compiler\Analyzer\Analyzer;
use Phel\Compiler\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Analyzer\Ast\MapNode;
use Phel\Compiler\Analyzer\Environment\GlobalEnvironment;
use Phel\Compiler\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Analyzer\TypeAnalyzer\AnalyzeMap;
use Phel\Lang\TypeFactory;
use PHPUnit\Framework\TestCase;

final class AnalyzePersistentMapTest extends TestCase
{
    private AnalyzeMap $mapAnalyzer;

    public function setUp(): void
    {
        $this->mapAnalyzer = new AnalyzeMap(new Analyzer(new GlobalEnvironment()));
    }

    public function testEmptyMap(): void
    {
        $env = NodeEnvironment::empty();
        self::assertEquals(
            new MapNode($env, [], null),
            $this->mapAnalyzer->analyze(TypeFactory::getInstance()->emptyPersistentMap(), $env)
        );
    }

    public function testMap(): void
    {
        $env = NodeEnvironment::empty();
        self::assertEquals(
            new MapNode($env, [
                new LiteralNode($env->withContext(NodeEnvironment::CONTEXT_EXPRESSION), 'a', null),
                new LiteralNode($env->withContext(NodeEnvironment::CONTEXT_EXPRESSION), 1, null),
            ], null),
            $this->mapAnalyzer->analyze(TypeFactory::getInstance()->persistentMapFromKVs('a', 1), $env)
        );
    }
}
