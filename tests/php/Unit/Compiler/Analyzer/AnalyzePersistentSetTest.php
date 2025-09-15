<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer;

use Phel;
use Phel\Compiler\Application\Analyzer;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Ast\SetNode;
use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironment;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\AnalyzePersistentSet;
use PHPUnit\Framework\TestCase;

final class AnalyzePersistentSetTest extends TestCase
{
    private AnalyzePersistentSet $setAnalyzer;

    protected function setUp(): void
    {
        $this->setAnalyzer = new AnalyzePersistentSet(new Analyzer(new GlobalEnvironment()));
    }

    public function test_empty_set(): void
    {
        $env = NodeEnvironment::empty();
        self::assertEquals(
            new SetNode($env, [], null),
            $this->setAnalyzer->analyze(Phel::set(), $env),
        );
    }

    public function test_set(): void
    {
        $env = NodeEnvironment::empty();
        self::assertEquals(
            new SetNode($env, [
                new LiteralNode($env->withDisallowRecurFrame()->withExpressionContext(), 1, null),
            ], null),
            $this->setAnalyzer->analyze(Phel::set([1]), $env),
        );
    }
}
