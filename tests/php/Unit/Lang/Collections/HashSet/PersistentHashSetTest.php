<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang\Collections\HashSet;

use Phel\Lang\Collections\HashSet\PersistentHashSet;
use Phel\Lang\Collections\HashSet\TransientHashSet;
use Phel\Lang\Collections\Map\PersistentArrayMap;
use Phel\Lang\Collections\Map\PersistentHashMap;
use PhelTest\Unit\Lang\Collections\ModuloHasher;
use PhelTest\Unit\Lang\Collections\SimpleEqualizer;
use PHPUnit\Framework\TestCase;

use function iterator_to_array;

final class PersistentHashSetTest extends TestCase
{
    public function test_empty(): void
    {
        $set = $this->emptySet();

        self::assertCount(0, $set);
        self::assertFalse($set->contains(1));
    }

    public function test_add_value(): void
    {
        $set = $this->emptySet()->add(1);

        self::assertCount(1, $set);
        self::assertTrue($set->contains(1));
    }

    public function test_add_returns_the_same_instance_for_a_duplicate(): void
    {
        $set = $this->emptySet()->add(1);

        self::assertSame($set, $set->add(1));
    }

    public function test_remove_existing_value(): void
    {
        $set = $this->emptySet()->add(1)->add(2)->remove(1);

        self::assertCount(1, $set);
        self::assertFalse($set->contains(1));
        self::assertTrue($set->contains(2));
    }

    public function test_remove_returns_the_same_instance_for_a_missing_value(): void
    {
        $set = $this->emptySet()->add(1);

        self::assertSame($set, $set->remove(2));
    }

    public function test_invoke_returns_the_member_or_null(): void
    {
        $set = $this->emptySet()->add(1);

        self::assertSame(1, $set(1));
        self::assertNull($set(2));
    }

    public function test_equals_ignores_insertion_order(): void
    {
        $a = $this->emptySet()->add(1)->add(2);
        $b = $this->emptySet()->add(2)->add(1);

        self::assertTrue($a->equals($b));
        self::assertTrue($b->equals($a));
    }

    public function test_equals_is_false_for_a_different_length(): void
    {
        $a = $this->emptySet()->add(1)->add(2);
        $b = $this->emptySet()->add(1);

        self::assertFalse($a->equals($b));
    }

    public function test_equals_is_false_for_a_different_type(): void
    {
        self::assertFalse($this->emptySet()->add(1)->equals('not a set'));
    }

    public function test_equals_is_reflexive_on_the_same_instance(): void
    {
        $set = $this->emptySet()->add(1);

        self::assertTrue($set->equals($set));
    }

    public function test_hash_is_stable_and_order_independent(): void
    {
        $a = $this->emptySet()->add(1)->add(2);
        $b = $this->emptySet()->add(2)->add(1);

        self::assertSame($a->hash(), $a->hash(), 'hash is cached, not recomputed differently');
        self::assertSame($a->hash(), $b->hash());
    }

    public function test_iterates_and_converts_to_a_php_array(): void
    {
        $set = $this->emptySet()->add(1)->add(2);

        self::assertSame([1, 2], iterator_to_array($set));
        self::assertSame([1, 2], $set->toPhpArray());
    }

    public function test_concat_adds_every_value(): void
    {
        $set = $this->emptySet()->add(1)->concat([2, 3, 1]);

        self::assertCount(3, $set);
        self::assertTrue($set->contains(2));
        self::assertTrue($set->contains(3));
    }

    public function test_as_transient_round_trip(): void
    {
        $transient = $this->emptySet()->add(1)->asTransient();

        self::assertInstanceOf(TransientHashSet::class, $transient);
        self::assertInstanceOf(PersistentHashSet::class, $transient->persistent());
    }

    public function test_with_meta_keeps_the_members(): void
    {
        $meta = PersistentArrayMap::empty(new ModuloHasher(), new SimpleEqualizer());
        $set = $this->emptySet()->add(1)->withMeta($meta);

        self::assertSame($meta, $set->getMeta());
        self::assertTrue($set->contains(1));
    }

    public function test_meta_is_null_by_default(): void
    {
        self::assertNull($this->emptySet()->getMeta());
    }

    /**
     * @return PersistentHashSet<mixed>
     */
    private function emptySet(): PersistentHashSet
    {
        $hasher = new ModuloHasher();

        return new PersistentHashSet($hasher, null, PersistentHashMap::empty($hasher, new SimpleEqualizer()));
    }
}
