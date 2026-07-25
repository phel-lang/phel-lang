<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang\Collections;

use Phel\Lang\Collections\HashSet\PersistentHashSet;
use Phel\Lang\Collections\Map\PersistentArrayMap;
use Phel\Lang\Collections\Map\PersistentHashMap;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Collections\SortedMap\PersistentSortedMap;
use Phel\Lang\Collections\SortedSet\PersistentSortedSet;
use Phel\Lang\Collections\Vector\PersistentVector;
use Phel\Lang\Keyword;
use PHPUnit\Framework\TestCase;

/**
 * Pins the `asTransient() -> persistent()` round trip on every collection that
 * has a transient: freezing a transient back must return the metadata the
 * source collection carried, exactly as `(persistent! (transient m))` does in
 * Clojure. Every persistent operation implemented through a transient (a map
 * `merge`, a vector or set `concat`, the compiler's `assoc`/`conj` chain
 * specialisation) inherits the answer from here.
 */
final class TransientMetaRoundTripTest extends TestCase
{
    public function test_hash_map_round_trip_keeps_meta(): void
    {
        $meta = $this->meta();
        $map = PersistentHashMap::empty(new ModuloHasher(), new SimpleEqualizer())
            ->put(1, 'a')
            ->withMeta($meta);

        self::assertSame($meta, $map->asTransient()->persistent()->getMeta());
    }

    public function test_array_map_round_trip_keeps_meta(): void
    {
        $meta = $this->meta();
        $map = PersistentArrayMap::empty(new ModuloHasher(), new SimpleEqualizer())
            ->put(1, 'a')
            ->withMeta($meta);

        self::assertSame($meta, $map->asTransient()->persistent()->getMeta());
    }

    public function test_sorted_map_round_trip_keeps_meta(): void
    {
        $meta = $this->meta();
        $map = PersistentSortedMap::empty(new ModuloHasher(), new SimpleEqualizer())
            ->put(1, 'a')
            ->withMeta($meta);

        self::assertSame($meta, $map->asTransient()->persistent()->getMeta());
    }

    public function test_vector_round_trip_keeps_meta(): void
    {
        $meta = $this->meta();
        $vector = PersistentVector::empty(new ModuloHasher(), new SimpleEqualizer())
            ->append(1)
            ->withMeta($meta);

        self::assertSame($meta, $vector->asTransient()->persistent()->getMeta());
    }

    public function test_hash_set_round_trip_keeps_meta(): void
    {
        $meta = $this->meta();
        $set = $this->emptyHashSet()->add(1)->withMeta($meta);

        self::assertSame($meta, $set->asTransient()->persistent()->getMeta());
    }

    public function test_sorted_set_round_trip_keeps_meta(): void
    {
        $meta = $this->meta();
        $set = $this->emptySortedSet()->add(1)->withMeta($meta);

        self::assertSame($meta, $set->asTransient()->persistent()->getMeta());
    }

    /**
     * An array map promotes to a hash map once it outgrows `MAX_SIZE` while the
     * transient is open. The promoted transient has to carry the metadata over,
     * or the round trip loses it only for large maps.
     */
    public function test_array_map_promoted_to_hash_map_keeps_meta(): void
    {
        $meta = $this->meta();
        $map = PersistentArrayMap::empty(new ModuloHasher(), new SimpleEqualizer());
        for ($i = 0; $i < PersistentArrayMap::MAX_SIZE; ++$i) {
            $map = $map->put($i, $i);
        }

        $transient = $map->withMeta($meta)->asTransient();
        $transient->put(PersistentArrayMap::MAX_SIZE, 'x');

        $frozen = $transient->persistent();

        self::assertInstanceOf(PersistentHashMap::class, $frozen);
        self::assertSame($meta, $frozen->getMeta());
    }

    public function test_vector_concat_keeps_meta(): void
    {
        $meta = $this->meta();
        $vector = PersistentVector::empty(new ModuloHasher(), new SimpleEqualizer())
            ->append(1)
            ->withMeta($meta);

        self::assertSame($meta, $vector->concat([2, 3])->getMeta());
    }

    public function test_hash_set_concat_keeps_meta(): void
    {
        $meta = $this->meta();
        $set = $this->emptyHashSet()->add(1)->withMeta($meta);

        self::assertSame($meta, $set->concat([2, 3])->getMeta());
    }

    public function test_sorted_set_concat_keeps_meta(): void
    {
        $meta = $this->meta();
        $set = $this->emptySortedSet()->add(1)->withMeta($meta);

        self::assertSame($meta, $set->concat([2, 3])->getMeta());
    }

    /**
     * A transient opened from nothing has no source to inherit from, so it must
     * still freeze into a collection without metadata.
     */
    public function test_a_transient_built_from_scratch_has_no_meta(): void
    {
        $map = PersistentHashMap::empty(new ModuloHasher(), new SimpleEqualizer());
        $vector = PersistentVector::empty(new ModuloHasher(), new SimpleEqualizer());

        self::assertNull($map->asTransient()->put(1, 'a')->persistent()->getMeta());
        self::assertNull($vector->asTransient()->append(1)->persistent()->getMeta());
    }

    /**
     * @return PersistentMapInterface<mixed, mixed>
     */
    private function meta(): PersistentMapInterface
    {
        return PersistentArrayMap::empty(new ModuloHasher(), new SimpleEqualizer())
            ->put(Keyword::create('tag'), 'x');
    }

    /**
     * @return PersistentHashSet<int>
     */
    private function emptyHashSet(): PersistentHashSet
    {
        $hasher = new ModuloHasher();

        return new PersistentHashSet($hasher, null, PersistentHashMap::empty($hasher, new SimpleEqualizer()));
    }

    /**
     * @return PersistentSortedSet<int>
     */
    private function emptySortedSet(): PersistentSortedSet
    {
        $hasher = new ModuloHasher();

        return new PersistentSortedSet($hasher, null, PersistentSortedMap::empty($hasher, new SimpleEqualizer()));
    }
}
