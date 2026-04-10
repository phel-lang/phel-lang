<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang\Collections\SortedSet;

use Phel\Lang\Collections\Map\PersistentArrayMap;
use Phel\Lang\Collections\SortedMap\PersistentSortedMap;
use Phel\Lang\Collections\SortedSet\PersistentSortedSet;
use PhelTest\Unit\Lang\Collections\ModuloHasher;
use PhelTest\Unit\Lang\Collections\SimpleEqualizer;
use PHPUnit\Framework\TestCase;

final class PersistentSortedSetTest extends TestCase
{
    public function test_empty(): void
    {
        $s = $this->emptySet();

        self::assertCount(0, $s);
        self::assertFalse($s->contains(1));
    }

    public function test_add_value(): void
    {
        $s = $this->emptySet()->add(1);

        self::assertCount(1, $s);
        self::assertTrue($s->contains(1));
    }

    public function test_add_duplicate(): void
    {
        $s = $this->emptySet()->add(1)->add(1);

        self::assertCount(1, $s);
    }

    public function test_add_returns_same_instance_when_duplicate(): void
    {
        $s = $this->emptySet()->add(1);
        $s2 = $s->add(1);

        self::assertSame($s, $s2);
    }

    public function test_remove_existing(): void
    {
        $s = $this->emptySet()->add(1)->remove(1);

        self::assertCount(0, $s);
        self::assertFalse($s->contains(1));
    }

    public function test_remove_non_existing(): void
    {
        $s = $this->emptySet()->add(1);
        $s2 = $s->remove(2);

        self::assertSame($s, $s2);
    }

    public function test_iteration_order_is_sorted(): void
    {
        $s = $this->emptySet()->add(3)->add(1)->add(2);

        $values = [];
        foreach ($s as $v) {
            $values[] = $v;
        }

        $this->assertSame([1, 2, 3], $values);
    }

    public function test_custom_comparator_reverse_order(): void
    {
        $s = $this->emptySet(static fn($a, $b): int => $b <=> $a)
            ->add(1)->add(3)->add(2);

        $values = [];
        foreach ($s as $v) {
            $values[] = $v;
        }

        $this->assertSame([3, 2, 1], $values);
    }

    public function test_equals(): void
    {
        $s1 = $this->emptySet()->add(1)->add(2)->add(3);
        $s2 = $this->emptySet()->add(3)->add(1)->add(2);

        $this->assertTrue($s1->equals($s2));
    }

    public function test_equals_different_elements(): void
    {
        $s1 = $this->emptySet()->add(1)->add(2);
        $s2 = $this->emptySet()->add(3)->add(4);

        $this->assertFalse($s1->equals($s2));
    }

    public function test_equals_different_count(): void
    {
        $s1 = $this->emptySet()->add(1)->add(2);
        $s2 = $this->emptySet()->add(1);

        $this->assertFalse($s1->equals($s2));
    }

    public function test_hash(): void
    {
        $s1 = $this->emptySet()->add(1)->add(2);
        $s2 = $this->emptySet()->add(2)->add(1);

        $this->assertSame($s1->hash(), $s2->hash());
    }

    public function test_invoke(): void
    {
        $s = $this->emptySet()->add(1)->add(2);

        self::assertSame(1, $s(1));
        self::assertNull($s(3));
    }

    public function test_with_meta(): void
    {
        $meta = PersistentArrayMap::empty(new ModuloHasher(), new SimpleEqualizer());
        $s = $this->emptySet()->withMeta($meta);

        $this->assertEquals($meta, $s->getMeta());
    }

    public function test_to_php_array(): void
    {
        $s = $this->emptySet()->add(3)->add(1)->add(2);

        $this->assertSame([1, 2, 3], $s->toPhpArray());
    }

    public function test_concat(): void
    {
        $s = $this->emptySet()->add(1);
        $s2 = $s->concat([3, 2]);

        $values = [];
        foreach ($s2 as $v) {
            $values[] = $v;
        }

        $this->assertSame([1, 2, 3], $values);
        self::assertCount(3, $s2);
    }

    public function test_transient_round_trip(): void
    {
        $s = $this->emptySet();
        $t = $s->asTransient();
        $t->add(3);
        $t->add(1);
        $t->add(2);

        $result = $t->persistent();

        $values = [];
        foreach ($result as $v) {
            $values[] = $v;
        }

        $this->assertSame([1, 2, 3], $values);
    }

    public function test_persistent_immutability(): void
    {
        $s1 = $this->emptySet()->add(1);
        $s2 = $s1->add(2);

        self::assertCount(1, $s1);
        self::assertCount(2, $s2);
    }

    private function emptySet(?callable $comparator = null): PersistentSortedSet
    {
        $map = PersistentSortedMap::empty(new ModuloHasher(), new SimpleEqualizer(), $comparator);

        return new PersistentSortedSet(new ModuloHasher(), null, $map);
    }
}
