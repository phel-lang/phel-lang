<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer;

use Phel\Compiler\Domain\Analyzer\Analyzer;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Ast\MapNode;
use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironment;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\AnalyzePersistentMap;
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
                new LiteralNode($env->withContext(NodeEnvironmentInterface::CONTEXT_EXPRESSION), 'a', null),
                new LiteralNode($env->withContext(NodeEnvironmentInterface::CONTEXT_EXPRESSION), 1, null),
            ], null),
            $this->mapAnalyzer->analyze(TypeFactory::getInstance()->persistentMapFromKVs('a', 1), $env)
        );
    }
}
