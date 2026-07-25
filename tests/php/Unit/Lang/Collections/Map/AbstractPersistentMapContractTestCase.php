<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang\Collections\Map;

use Phel\Lang\Collections\Map\PersistentArrayMap;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use PhelTest\Unit\Lang\Collections\ModuloHasher;
use PhelTest\Unit\Lang\Collections\SimpleEqualizer;
use PHPUnit\Framework\TestCase;

/**
 * The behaviour every `PersistentMapInterface` implementation owes its callers,
 * regardless of how it stores its entries.
 *
 * Each implementation runs this whole suite through its own subclass, so a
 * behaviour that drifts in one implementation fails there instead of quietly
 * being covered in only one of the three test classes. Anything that is a
 * property of *one* implementation (iteration order, node promotion, the
 * comparator, the "uneven number of elements" wording) belongs in that
 * implementation's own test class, not here.
 */
abstract class AbstractPersistentMapContractTestCase extends TestCase
{
    public function test_empty_map_holds_nothing(): void
    {
        $map = $this->emptyMap();

        self::assertCount(0, $map);
        self::assertFalse($map->contains('test'));
        self::assertFalse($map->contains(null));
        self::assertNull($map->find('test'));
    }

    public function test_put_stores_the_value_under_its_key(): void
    {
        $map = $this->emptyMap()->put(1, 'test');

        self::assertCount(1, $map);
        self::assertTrue($map->contains(1));
        self::assertSame('test', $map->find(1));
    }

    public function test_put_leaves_the_source_map_untouched(): void
    {
        $map = $this->emptyMap();
        $withEntry = $map->put(1, 'test');

        self::assertCount(0, $map);
        self::assertFalse($map->contains(1));
        self::assertNull($map->find(1));
        self::assertCount(1, $withEntry);
    }

    public function test_put_same_key_and_value_twice_keeps_one_entry(): void
    {
        $map = $this->emptyMap()
            ->put(1, 'test')
            ->put(1, 'test');

        self::assertCount(1, $map);
        self::assertTrue($map->contains(1));
        self::assertSame('test', $map->find(1));
    }

    public function test_put_same_key_with_a_new_value_replaces_it(): void
    {
        $map = $this->emptyMap()
            ->put(1, 'test')
            ->put(1, 'foo');

        self::assertCount(1, $map);
        self::assertTrue($map->contains(1));
        self::assertSame('foo', $map->find(1));
    }

    public function test_null_is_a_usable_key(): void
    {
        $map = $this->emptyMap();
        $withNull = $map->put(null, 'test');

        self::assertNull($map->find(null));
        self::assertCount(0, $map);
        self::assertFalse($map->contains(null));
        self::assertSame('test', $withNull->find(null));
        self::assertCount(1, $withNull);
        self::assertTrue($withNull->contains(null));
    }

    public function test_put_null_key_twice_keeps_one_entry(): void
    {
        $map = $this->emptyMap()
            ->put(null, 'test')
            ->put(null, 'test');

        self::assertCount(1, $map);
        self::assertTrue($map->contains(null));
        self::assertSame('test', $map->find(null));
    }

    public function test_remove_drops_an_existing_key(): void
    {
        $map = $this->emptyMap()
            ->put(1, 'test')
            ->remove(1);

        self::assertCount(0, $map);
        self::assertFalse($map->contains(1));
        self::assertNull($map->find(1));
    }

    public function test_remove_drops_an_existing_null_key(): void
    {
        $map = $this->emptyMap()
            ->put(null, 'test')
            ->remove(null);

        self::assertCount(0, $map);
        self::assertFalse($map->contains(null));
        self::assertNull($map->find(null));
    }

    public function test_remove_of_an_absent_key_on_an_empty_map_is_a_no_op(): void
    {
        $map = $this->emptyMap()->remove(1);

        self::assertCount(0, $map);
        self::assertFalse($map->contains(1));
        self::assertNull($map->find(1));
    }

    public function test_remove_of_an_absent_null_key_is_a_no_op(): void
    {
        $map = $this->emptyMap()->remove(null);

        self::assertCount(0, $map);
        self::assertFalse($map->contains(null));
        self::assertNull($map->find(null));
    }

    public function test_remove_of_an_absent_key_keeps_the_other_entries(): void
    {
        $map = $this->emptyMap()
            ->put(2, 'test')
            ->remove(1);

        self::assertCount(1, $map);
        self::assertTrue($map->contains(2));
        self::assertSame('test', $map->find(2));
        self::assertFalse($map->contains(1));
        self::assertNull($map->find(1));
    }

    public function test_equals_ignores_insertion_order(): void
    {
        $one = $this->emptyMap()->put(1, 'foo')->put(2, 'bar');
        $two = $this->emptyMap()->put(2, 'bar')->put(1, 'foo');

        self::assertTrue($one->equals($two));
        self::assertTrue($two->equals($one));
    }

    public function test_equals_is_false_for_different_keys(): void
    {
        $one = $this->emptyMap()->put(1, 'foo')->put(2, 'bar');
        $two = $this->emptyMap()->put(3, 'bar')->put(4, 'foo');

        self::assertFalse($one->equals($two));
        self::assertFalse($two->equals($one));
    }

    public function test_equals_is_false_for_different_lengths(): void
    {
        $one = $this->emptyMap()->put(1, 'foo')->put(2, 'bar')->put(3, 'foobar');
        $two = $this->emptyMap()->put(2, 'bar')->put(1, 'foo');

        self::assertFalse($one->equals($two));
        self::assertFalse($two->equals($one));
    }

    public function test_equals_is_false_for_different_values(): void
    {
        $one = $this->emptyMap()->put(1, 'foo')->put(2, 'bar');
        $two = $this->emptyMap()->put(1, 'bar')->put(2, 'foo');

        self::assertFalse($one->equals($two));
        self::assertFalse($two->equals($one));
    }

    public function test_equals_is_false_against_a_plain_php_array(): void
    {
        $map = $this->emptyMap()->put(1, 'foo')->put(2, 'bar');

        self::assertFalse($map->equals([1 => 'foo', 2 => 'bar']));
    }

    public function test_hash_of_an_empty_map(): void
    {
        self::assertSame(1, $this->emptyMap()->hash());
    }

    public function test_hash_of_a_single_entry_map(): void
    {
        $map = $this->emptyMap()->put(1, 10);

        self::assertSame(1 + (1 ^ 10), $map->hash());
    }

    public function test_iterating_an_empty_map_yields_nothing(): void
    {
        $collected = [];
        foreach ($this->emptyMap() as $key => $value) {
            $collected[$key] = $value;
        }

        self::assertSame([], $collected);
    }

    public function test_iterating_yields_every_stored_entry(): void
    {
        $map = $this->emptyMap()->put(1, 'foo')->put(2, 'bar')->put(3, 'foobar');

        $collected = [];
        foreach ($map as $key => $value) {
            $collected[$key] = $value;
        }

        ksort($collected);

        self::assertSame([1 => 'foo', 2 => 'bar', 3 => 'foobar'], $collected);
    }

    public function test_with_meta_is_readable_back(): void
    {
        $meta = PersistentArrayMap::empty(new ModuloHasher(), new SimpleEqualizer());
        $map = $this->emptyMap()->withMeta($meta);

        self::assertEquals($meta, $map->getMeta());
    }

    public function test_a_map_is_callable_as_a_lookup(): void
    {
        $map = $this->emptyMap()->put(1, 'test');

        self::assertSame('test', $map(1));
        self::assertNull($map(2));
    }

    public function test_array_access_reads_a_value(): void
    {
        $map = $this->emptyMap()->put(1, 'test');

        self::assertSame('test', $map[1]);
        self::assertNull($map[2]);
    }

    public function test_array_access_reports_key_presence(): void
    {
        $map = $this->emptyMap()->put(1, 'test');

        self::assertArrayHasKey(1, $map);
        self::assertArrayNotHasKey(2, $map);
    }

    /**
     * @return PersistentMapInterface<mixed, mixed>
     */
    abstract protected function emptyMap(): PersistentMapInterface;
}
