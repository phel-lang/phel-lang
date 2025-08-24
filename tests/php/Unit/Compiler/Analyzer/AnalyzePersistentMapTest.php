<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer;

use Phel;
use Phel\Compiler\Application\Analyzer;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Ast\MapNode;
use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironment;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\AnalyzePersistentMap;
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
            $this->mapAnalyzer->analyze(Phel::emptyPersistentMap(), $env),
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
            $this->mapAnalyzer->analyze(Phel::persistentMapFromKVs('a', 1), $env),
        );
    }
}
