<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer;

use Phel;
use Phel\Compiler\Application\Analyzer;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Ast\VectorNode;
use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironment;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\AnalyzePersistentVector;
use PHPUnit\Framework\TestCase;

final class AnalyzePersistentVectorTest extends TestCase
{
    private AnalyzePersistentVector $vectorAnalzyer;

    protected function setUp(): void
    {
        $this->vectorAnalzyer = new AnalyzePersistentVector(new Analyzer(new GlobalEnvironment()));
    }

    public function test_empty_vector(): void
    {
        $env = NodeEnvironment::empty();
        self::assertEquals(
            new VectorNode($env, []),
            $this->vectorAnalzyer->analyze(Phel::vector(), $env),
        );
    }

    public function test_vector(): void
    {
        $env = NodeEnvironment::empty();
        self::assertEquals(
            new VectorNode($env, [
                new LiteralNode($env->withDisallowRecurFrame()->withExpressionContext(), 1),
            ]),
            $this->vectorAnalzyer->analyze(Phel::vector([1]), $env),
        );
    }
}
