<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Domain\Emitter\OutputEmitter\ConstantHoisting;

use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Ast\LocalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\MapNode;
use Phel\Compiler\Domain\Analyzer\Ast\SetNode;
use Phel\Compiler\Domain\Analyzer\Ast\VectorNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Emitter\OutputEmitter\Cache\ConstantScope;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;
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

    public function test_structurally_equal_collections_share_one_slot(): void
    {
        $scope = new ConstantScope();
        $first = new VectorNode(NodeEnvironment::empty(), [
            new LiteralNode(NodeEnvironment::empty(), 1),
            new LiteralNode(NodeEnvironment::empty(), 2),
        ]);
        $second = new VectorNode(NodeEnvironment::empty(), [
            new LiteralNode(NodeEnvironment::empty(), 1),
            new LiteralNode(NodeEnvironment::empty(), 2),
        ]);

        self::assertSame(0, $scope->reserve($first));
        self::assertSame(0, $scope->reserve($second));
        self::assertSame(0, $scope->lookup($first));
        self::assertSame(0, $scope->lookup($second));
        self::assertSame(1, $scope->count());
    }

    public function test_structurally_equal_maps_share_one_slot(): void
    {
        $scope = new ConstantScope();
        $first = new MapNode(NodeEnvironment::empty(), [
            new LiteralNode(NodeEnvironment::empty(), Keyword::create('a')),
            new LiteralNode(NodeEnvironment::empty(), 1),
        ]);
        $second = new MapNode(NodeEnvironment::empty(), [
            new LiteralNode(NodeEnvironment::empty(), Keyword::create('a')),
            new LiteralNode(NodeEnvironment::empty(), 1),
        ]);

        self::assertSame(0, $scope->reserve($first));
        self::assertSame(0, $scope->reserve($second));
        self::assertSame(1, $scope->count());
    }

    public function test_nested_structurally_equal_collections_share_one_slot(): void
    {
        $scope = new ConstantScope();
        $first = new VectorNode(NodeEnvironment::empty(), [
            new VectorNode(NodeEnvironment::empty(), [
                new LiteralNode(NodeEnvironment::empty(), 1),
            ]),
        ]);
        $second = new VectorNode(NodeEnvironment::empty(), [
            new VectorNode(NodeEnvironment::empty(), [
                new LiteralNode(NodeEnvironment::empty(), 1),
            ]),
        ]);

        self::assertSame(0, $scope->reserve($first));
        self::assertSame(0, $scope->reserve($second));
        self::assertSame(1, $scope->count());
    }

    public function test_same_elements_in_different_collection_shapes_do_not_collide(): void
    {
        $scope = new ConstantScope();
        $vector = new VectorNode(NodeEnvironment::empty(), [
            new LiteralNode(NodeEnvironment::empty(), 1),
        ]);
        $set = new SetNode(NodeEnvironment::empty(), [
            new LiteralNode(NodeEnvironment::empty(), 1),
        ]);

        self::assertSame(0, $scope->reserve($vector));
        self::assertSame(1, $scope->reserve($set));
        self::assertSame(2, $scope->count());
    }

    public function test_collection_with_non_literal_child_stays_identity_keyed(): void
    {
        $scope = new ConstantScope();
        $first = new VectorNode(NodeEnvironment::empty(), [
            new LocalVarNode(NodeEnvironment::empty(), Symbol::create('x')),
        ]);
        $second = new VectorNode(NodeEnvironment::empty(), [
            new LocalVarNode(NodeEnvironment::empty(), Symbol::create('x')),
        ]);

        self::assertSame(0, $scope->reserve($first));
        self::assertSame(1, $scope->reserve($second));
        self::assertSame(2, $scope->count());
    }

    public function test_string_element_cannot_forge_a_colliding_collection_key(): void
    {
        // `["a,str:b"]` (one string) and `["a" "b"]` (two strings) would share
        // a raw comma-joined digest; length-prefixing keeps them on distinct
        // slots so neither corrupts the other's cached value.
        $scope = new ConstantScope();
        $forged = new VectorNode(NodeEnvironment::empty(), [
            new LiteralNode(NodeEnvironment::empty(), 'a,str:b'),
        ]);
        $twoStrings = new VectorNode(NodeEnvironment::empty(), [
            new LiteralNode(NodeEnvironment::empty(), 'a'),
            new LiteralNode(NodeEnvironment::empty(), 'b'),
        ]);

        self::assertSame(0, $scope->reserve($forged));
        self::assertSame(1, $scope->reserve($twoStrings));
        self::assertSame(2, $scope->count());
    }

    public function test_nan_float_literal_is_not_value_keyed(): void
    {
        // NaN != NaN, so two NaN literals must never collapse onto one slot.
        $scope = new ConstantScope();
        $first = new LiteralNode(NodeEnvironment::empty(), NAN);
        $second = new LiteralNode(NodeEnvironment::empty(), NAN);

        self::assertSame(0, $scope->reserve($first));
        self::assertSame(1, $scope->reserve($second));
        self::assertSame(2, $scope->count());
    }

    public function test_vector_containing_nan_is_not_interned(): void
    {
        // A NaN child bails the whole literal to identity-keying, so two
        // `["a" ##NaN]` vectors stay distinct instances and compare unequal.
        $scope = new ConstantScope();
        $first = new VectorNode(NodeEnvironment::empty(), [
            new LiteralNode(NodeEnvironment::empty(), 'a'),
            new LiteralNode(NodeEnvironment::empty(), NAN),
        ]);
        $second = new VectorNode(NodeEnvironment::empty(), [
            new LiteralNode(NodeEnvironment::empty(), 'a'),
            new LiteralNode(NodeEnvironment::empty(), NAN),
        ]);

        self::assertSame(0, $scope->reserve($first));
        self::assertSame(1, $scope->reserve($second));
        self::assertSame(2, $scope->count());
    }

    public function test_set_containing_nan_is_not_interned(): void
    {
        $scope = new ConstantScope();
        $first = new SetNode(NodeEnvironment::empty(), [
            new LiteralNode(NodeEnvironment::empty(), 1.0),
            new LiteralNode(NodeEnvironment::empty(), NAN),
        ]);
        $second = new SetNode(NodeEnvironment::empty(), [
            new LiteralNode(NodeEnvironment::empty(), 1.0),
            new LiteralNode(NodeEnvironment::empty(), NAN),
        ]);

        self::assertSame(0, $scope->reserve($first));
        self::assertSame(1, $scope->reserve($second));
        self::assertSame(2, $scope->count());
    }

    public function test_finite_float_collection_still_shares_one_slot(): void
    {
        // Only NaN bails; a finite float keeps interning, proving the guard is
        // NaN-specific and not a blanket opt-out for float-bearing literals.
        $scope = new ConstantScope();
        $first = new VectorNode(NodeEnvironment::empty(), [
            new LiteralNode(NodeEnvironment::empty(), 'a'),
            new LiteralNode(NodeEnvironment::empty(), 1.5),
        ]);
        $second = new VectorNode(NodeEnvironment::empty(), [
            new LiteralNode(NodeEnvironment::empty(), 'a'),
            new LiteralNode(NodeEnvironment::empty(), 1.5),
        ]);

        self::assertSame(0, $scope->reserve($first));
        self::assertSame(0, $scope->reserve($second));
        self::assertSame(1, $scope->count());
    }
}
