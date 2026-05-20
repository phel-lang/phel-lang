<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Domain\Emitter\OutputEmitter\ConstantHoisting;

use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Emitter\OutputEmitter\Cache\ConstantScope;
use PHPUnit\Framework\TestCase;

final class ConstantScopeTest extends TestCase
{
    public function test_starts_empty(): void
    {
        $scope = new ConstantScope();
        self::assertSame(0, $scope->count());
    }

    public function test_reserve_assigns_sequential_ids(): void
    {
        $scope = new ConstantScope();
        $a = new LiteralNode(NodeEnvironment::empty(), 1);
        $b = new LiteralNode(NodeEnvironment::empty(), 2);

        self::assertSame(0, $scope->reserve($a));
        self::assertSame(1, $scope->reserve($b));
        self::assertSame(2, $scope->count());
    }

    public function test_reserve_is_idempotent_per_node(): void
    {
        $scope = new ConstantScope();
        $node = new LiteralNode(NodeEnvironment::empty(), 1);

        self::assertSame(0, $scope->reserve($node));
        self::assertSame(0, $scope->reserve($node));
        self::assertSame(1, $scope->count());
    }

    public function test_lookup_returns_null_for_unknown_node(): void
    {
        $scope = new ConstantScope();
        $node = new LiteralNode(NodeEnvironment::empty(), 1);

        self::assertNull($scope->lookup($node));
    }

    public function test_lookup_returns_reserved_slot(): void
    {
        $scope = new ConstantScope();
        $node = new LiteralNode(NodeEnvironment::empty(), 1);
        $scope->reserve($node);

        self::assertSame(0, $scope->lookup($node));
    }
}
