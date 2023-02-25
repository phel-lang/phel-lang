<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer;

use Phel\Compiler\Domain\Analyzer\Analyzer;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Ast\VectorNode;
use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironment;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\AnalyzePersistentVector;
use Phel\Lang\TypeFactory;
use PHPUnit\Framework\TestCase;

final class AnalyzePersistentVectorTest extends TestCase
{
    private AnalyzePersistentVector $vectorAnalzyer;

    public function setUp(): void
    {
        $this->vectorAnalzyer = new AnalyzePersistentVector(new Analyzer(new GlobalEnvironment()));
    }

    public function test_empty_vector(): void
    {
        $env = NodeEnvironment::empty();
        self::assertEquals(
            new VectorNode($env, [], null),
            $this->vectorAnalzyer->analyze(TypeFactory::getInstance()->emptyPersistentVector(), $env),
        );
    }

    public function test_vector(): void
    {
        $env = NodeEnvironment::empty();
        self::assertEquals(
            new VectorNode($env, [
                new LiteralNode($env->withDisallowRecurFrame()->withExpressionContext(), 1, null),
            ], null),
            $this->vectorAnalzyer->analyze(TypeFactory::getInstance()->persistentVectorFromArray([1]), $env),
        );
    }
}
