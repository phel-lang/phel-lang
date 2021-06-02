<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer;

use Phel\Compiler\Analyzer\Analyzer;
use Phel\Compiler\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Analyzer\Ast\MapNode;
use Phel\Compiler\Analyzer\Environment\GlobalEnvironment;
use Phel\Compiler\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Analyzer\TypeAnalyzer\AnalyzePersistentMap;
use Phel\Lang\TypeFactory;
use PHPUnit\Framework\TestCase;

final class AnalyzePersistentMapTest extends TestCase
{
    private AnalyzePersistentMap $mapAnalyzer;

    public function setUp(): void
    {
        $this->mapAnalyzer = new AnalyzePersistentMap(new Analyzer(new GlobalEnvironment()));
    }

    public function test_empty_map(): void
    {
        $env = NodeEnvironment::empty();
        self::assertEquals(
            new MapNode($env, [], null),
            $this->mapAnalyzer->analyze(TypeFactory::getInstance()->emptyPersistentMap(), $env)
        );
    }

    public function test_map(): void
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
