<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang\Collections\Map;

use Phel\Lang\Collections\Map\PersistentArrayMap;
use Phel\Lang\Collections\Map\PersistentHashMap;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use PhelTest\Unit\Lang\Collections\ModuloHasher;
use PhelTest\Unit\Lang\Collections\SimpleEqualizer;
use RuntimeException;

/**
 * The shared map behaviour lives in the contract case; this class covers what
 * is specific to the flat-array storage: insertion-order iteration and the
 * promotion to a hash map once `MAX_SIZE` is exceeded.
 */
final class PersistentArrayMapTest extends AbstractPersistentMapContractTestCase
{
    public function test_can_not_create_from_array_with_uneven_values(): void
    {
        $this->expectException(RuntimeException::class);
        // The message names the offending count: "odd number of elements" is
        // the whole diagnosis, so a bare "invalid argument" would not do.
        $this->expectExceptionMessage('An even number of elements must be provided to build a map, got 1');
        PersistentArrayMap::fromArray(new ModuloHasher(), new SimpleEqualizer(), ['test']);
    }

    public function test_iteration_follows_insertion_order(): void
    {
        $h = PersistentArrayMap::empty(new ModuloHasher(), new SimpleEqualizer())
            ->put(3, 'foobar')
            ->put(1, 'foo')
            ->put(2, 'bar');

        $result = [];
        foreach ($h as $k => $v) {
            $result[] = $k;
        }

        $this->assertSame([3, 1, 2], $result);
    }

    /**
     * Regression: a map carrying a NaN key was not equal to *itself*, because
     * the element walk compares NaN against NaN (never `=`). The `$this ===
     * $other` identity short-circuit — which the equals() comment already
     * promised — makes a map reflexively equal again. Stays here rather than
     * in the contract case because a NaN key is only storable without hashing
     * it, which the flat-array storage is alone in doing.
     */
    public function test_equals_is_reflexive_with_nan_key(): void
    {
        $h = PersistentArrayMap::empty(new ModuloHasher(), new SimpleEqualizer())
            ->put(NAN, 'foo')
            ->put(1, 'bar');

        $this->assertTrue($h->equals($h));
    }

    public function test_convert_to_persistent_hash_map(): void
    {
        $h = PersistentArrayMap::empty(new ModuloHasher(), new SimpleEqualizer());
        for ($i = 0; $i < PersistentArrayMap::MAX_SIZE + 1; ++$i) {
            $h = $h->put($i, 'foo');
        }

        $this->assertInstanceOf(PersistentHashMap::class, $h);
    }

    protected function emptyMap(): PersistentMapInterface
    {
        return PersistentArrayMap::empty(new ModuloHasher(), new SimpleEqualizer());
    }
}
