<?php

declare(strict_types=1);

namespace PhelTest\Unit\Transpiler\Analyzer;

use Phel\Lang\TypeFactory;
use Phel\Transpiler\Domain\Analyzer\Analyzer;
use Phel\Transpiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Transpiler\Domain\Analyzer\Ast\MapNode;
use Phel\Transpiler\Domain\Analyzer\Environment\GlobalEnvironment;
use Phel\Transpiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Transpiler\Domain\Analyzer\TypeAnalyzer\AnalyzePersistentMap;
use PHPUnit\Framework\TestCase;

final class AnalyzePersistentMapTest extends TestCase
{
    private AnalyzePersistentMap $mapAnalyzer;

    protected function setUp(): void
    {
        $this->mapAnalyzer = new AnalyzePersistentMap(new Analyzer(new GlobalEnvironment()));
    }

    public function test_empty_map(): void
    {
        $env = NodeEnvironment::empty();
        self::assertEquals(
            new MapNode($env, [], null),
            $this->mapAnalyzer->analyze(TypeFactory::getInstance()->emptyPersistentMap(), $env),
        );
    }

    public function test_map(): void
    {
        $env = NodeEnvironment::empty();
        self::assertEquals(
            new MapNode($env, [
                new LiteralNode($env->withExpressionContext(), 'a', null),
                new LiteralNode($env->withExpressionContext(), 1, null),
            ], null),
            $this->mapAnalyzer->analyze(TypeFactory::getInstance()->persistentMapFromKVs('a', 1), $env),
        );
    }
}
