<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Domain\Emitter\OutputEmitter\ConstantHoisting;

use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Ast\VectorNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Emitter\OutputEmitter\Cache\ConstantScope;
use Phel\Lang\Keyword;
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

    public function test_same_keyword_value_shares_one_slot(): void
    {
        $scope = new ConstantScope();
        $first = new LiteralNode(NodeEnvironment::empty(), Keyword::create('foo'));
        $second = new LiteralNode(NodeEnvironment::empty(), Keyword::create('foo'));

        self::assertSame(0, $scope->reserve($first));
        self::assertSame(0, $scope->reserve($second));
        self::assertSame(0, $scope->lookup($first));
        self::assertSame(0, $scope->lookup($second));
        self::assertSame(1, $scope->count());
    }

    public function test_distinct_keywords_get_distinct_slots(): void
    {
        $scope = new ConstantScope();
        $a = new LiteralNode(NodeEnvironment::empty(), Keyword::create('a'));
        $b = new LiteralNode(NodeEnvironment::empty(), Keyword::create('b'));

        self::assertSame(0, $scope->reserve($a));
        self::assertSame(1, $scope->reserve($b));
        self::assertSame(2, $scope->count());
    }

    public function test_namespaced_keyword_does_not_collide_with_bare_name(): void
    {
        $scope = new ConstantScope();
        $bare = new LiteralNode(NodeEnvironment::empty(), Keyword::create('foo'));
        $namespaced = new LiteralNode(NodeEnvironment::empty(), Keyword::create('foo', 'my-ns'));

        self::assertSame(0, $scope->reserve($bare));
        self::assertSame(1, $scope->reserve($namespaced));
        self::assertSame(2, $scope->count());
    }

    public function test_same_scalar_value_shares_one_slot(): void
    {
        $scope = new ConstantScope();
        $first = new LiteralNode(NodeEnvironment::empty(), 42);
        $second = new LiteralNode(NodeEnvironment::empty(), 42);

        self::assertSame(0, $scope->reserve($first));
        self::assertSame(0, $scope->reserve($second));
        self::assertSame(1, $scope->count());
    }

    public function test_int_and_same_looking_string_do_not_collide(): void
    {
        $scope = new ConstantScope();
        $int = new LiteralNode(NodeEnvironment::empty(), 1);
        $string = new LiteralNode(NodeEnvironment::empty(), '1');

        self::assertSame(0, $scope->reserve($int));
        self::assertSame(1, $scope->reserve($string));
        self::assertSame(2, $scope->count());
    }

    public function test_collection_literals_stay_identity_keyed(): void
    {
        $scope = new ConstantScope();
        $first = new VectorNode(NodeEnvironment::empty(), [
            new LiteralNode(NodeEnvironment::empty(), 1),
        ]);
        $second = new VectorNode(NodeEnvironment::empty(), [
            new LiteralNode(NodeEnvironment::empty(), 1),
        ]);

        self::assertSame(0, $scope->reserve($first));
        self::assertSame(1, $scope->reserve($second));
        self::assertSame(2, $scope->count());
    }
}
