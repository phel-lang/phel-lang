<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang\Collections\Vector;

use InvalidArgumentException;
use Iterator;
use Phel;
use Phel\Lang\Collections\Exceptions\IndexOutOfBoundsException;
use Phel\Lang\Collections\Map\PersistentHashMap;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Collections\Vector\PersistentVector;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\Collections\Vector\RangeIterator;
use Phel\Lang\Collections\Vector\SubVector;
use Phel\Lang\Collections\Vector\TransientVector;
use Phel\Lang\Equalizer;
use Phel\Lang\Hasher;
use PhelTest\Unit\Lang\Collections\ModuloHasher;
use PhelTest\Unit\Lang\Collections\SimpleEqualizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PersistentVectorTest extends TestCase
{
    public function test_append_to_tail(): void
    {
        $vEmpty = PersistentVector::empty(new ModuloHasher(), new SimpleEqualizer());
        $v1 = $vEmpty->append('a');

        $this->assertCount(0, $vEmpty);
        $this->assertCount(1, $v1);
        $this->assertSame('a', $v1->get(0));
    }

    public function test_append_tail_is_full(): void
    {
        $v1 = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), range(0, 31));
        $v2 = $v1->append(32);

        $this->assertCount(32, $v1);
        $this->assertCount(33, $v2);
        $this->assertSame(32, $v2->get(32));
    }

    public function test_append_overflow_root(): void
    {
        $initialLength = 32 + (32 * 32) - 1;
        $v1 = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), range(0, $initialLength));
        $v2 = $v1->append(1056);

        $this->assertCount(1056, $v1);
        $this->assertCount(1057, $v2);
        $this->assertSame(1056, $v2->get(1056));
    }

    public function test_append_tail_is_full_second_level(): void
    {
        $initialLength = 32 + (32 * 32) + 32 - 1;
        $v1 = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), range(0, $initialLength));
        $v2 = $v1->append($initialLength + 1);

        $this->assertCount($initialLength + 1, $v1);
        $this->assertCount($initialLength + 2, $v2);
        $this->assertSame($initialLength + 1, $v2->get($initialLength + 1));
    }

    public function test_append_tail_is_full_third_level(): void
    {
        $initialLength = 32 + (32 * 32) + (32 * 32 * 32) - 1;
        $v1 = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), range(0, $initialLength));
        $v2 = $v1->append($initialLength + 1);

        $this->assertCount($initialLength + 1, $v1);
        $this->assertCount($initialLength + 2, $v2);
        $this->assertSame($initialLength + 1, $v2->get($initialLength + 1));
    }

    public function test_update_out_of_range(): void
    {
        $this->expectException(IndexOutOfBoundsException::class);
        $v = PersistentVector::empty(new ModuloHasher(), new SimpleEqualizer());

        $v->update(1, 10);
    }

    public function test_update_append(): void
    {
        $vEmpty = PersistentVector::empty(new ModuloHasher(), new SimpleEqualizer());
        $v1 = $vEmpty->update(0, 10);

        $this->assertCount(0, $vEmpty);
        $this->assertCount(1, $v1);
        $this->assertSame(10, $v1->get(0));
    }

    public function test_update_in_tail(): void
    {
        $v1 = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), [10]);
        $v2 = $v1->update(0, 20);

        $this->assertCount(1, $v1);
        $this->assertCount(1, $v2);
        $this->assertSame(10, $v1->get(0));
        $this->assertSame(20, $v2->get(0));
    }

    public function test_update_in_level_tree(): void
    {
        $v1 = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), range(0, 32));
        $v2 = $v1->update(0, 20);

        $this->assertCount(33, $v1);
        $this->assertCount(33, $v2);
        $this->assertSame(0, $v1->get(0));
        $this->assertSame(20, $v2->get(0));
    }

    public function test_get_out_of_range(): void
    {
        $this->expectException(IndexOutOfBoundsException::class);
        $vEmpty = PersistentVector::empty(new ModuloHasher(), new SimpleEqualizer());

        $vEmpty->get(0);
    }

    public function test_pop_on_empty_vector(): void
    {
        $this->expectException(RuntimeException::class);
        $vEmpty = PersistentVector::empty(new ModuloHasher(), new SimpleEqualizer());

        $vEmpty->pop();
    }

    public function test_pop_on_one_element_vector(): void
    {
        $v1 = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), [1]);
        $vEmpty = $v1->pop();

        $this->assertCount(1, $v1);
        $this->assertCount(0, $vEmpty);
    }

    public function test_pop_from_tail(): void
    {
        $v1 = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), [1, 2]);
        $v2 = $v1->pop();

        $this->assertCount(2, $v1);
        $this->assertCount(1, $v2);
        $this->assertSame(1, $v2->get(0));
    }

    public function test_pop_from_tree_level_one(): void
    {
        $v1 = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), range(0, 32));
        $v2 = $v1->pop();

        $this->assertCount(33, $v1);
        $this->assertCount(32, $v2);
    }

    public function test_pop_from_tree_level_two(): void
    {
        $length = 32 + (32 * 32);
        $v1 = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), range(0, $length));
        $v2 = $v1->pop();

        $this->assertCount($length + 1, $v1);
        $this->assertCount($length, $v2);
    }

    public function test_pop_from_tree_level_two2(): void
    {
        $length = (32 * 32);
        $v1 = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), range(0, $length));
        $v2 = $v1->pop();

        $this->assertCount($length + 1, $v1);
        $this->assertCount($length, $v2);
    }

    public function test_pop_from_tree_level_three(): void
    {
        $length = (32 * 32) + (32 * 32 * 31) + 32;
        $v1 = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), range(0, $length));
        $v2 = $v1->pop();

        $this->assertCount($length + 1, $v1);
        $this->assertCount($length, $v2);
    }

    public function test_to_array_tail(): void
    {
        $arr = [1, 2, 3];
        $this->assertSame($arr, PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), $arr)->toArray());
    }

    public function test_to_array_level_one(): void
    {
        $arr = range(0, 32);
        $this->assertSame($arr, PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), $arr)->toArray());
    }

    public function test_get_iterator_on_empty_vector(): void
    {
        $v = PersistentVector::empty(new ModuloHasher(), new SimpleEqualizer());
        $result = [];
        foreach ($v as $x) {
            $result[] = $x;
        }

        $this->assertSame([], $result);
    }

    public function test_get_iterator_on_tail_only_vector(): void
    {
        $v = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), [1, 2]);
        $result = [];
        foreach ($v as $x) {
            $result[] = $x;
        }

        $this->assertSame([1, 2], $result);
    }

    public function test_get_iterator_on_tree_vector(): void
    {
        $v = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), range(0, 32));
        $result = [];
        $indices = [];
        foreach ($v as $index => $x) {
            $indices[] = $index;
            $result[] = $x;
        }

        $this->assertSame(range(0, 32), $result);
        $this->assertSame(range(0, 32), $indices);
    }

    public function test_get_range_iterator(): void
    {
        $this->assertInstanceOf(RangeIterator::class, PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), [1, 2, 3])->getRangeIterator(0, 2));
    }

    public function test_add_meta_data(): void
    {
        $meta = Phel::map();
        $vector = PersistentVector::empty(new ModuloHasher(), new SimpleEqualizer());
        $vectorWithMeta = $vector->withMeta($meta);

        $this->assertNotInstanceOf(PersistentMapInterface::class, $vector->getMeta());
        $this->assertEquals($meta, $vectorWithMeta->getMeta());
    }

    public function test_cdr_on_empty_vector(): void
    {
        $vector = PersistentVector::empty(new ModuloHasher(), new SimpleEqualizer());
        $this->assertNotInstanceOf(SubVector::class, $vector->cdr());
    }

    public function test_cdr_on_one_element_vector(): void
    {
        $vector = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), [1]);
        $this->assertNotInstanceOf(PersistentVectorInterface::class, $vector->cdr());
    }

    public function test_cdr_on_two_element_vector(): void
    {
        $vector = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), [1, 2]);

        $result = [];
        foreach ($vector->cdr() as $x) {
            $result[] = $x;
        }

        $this->assertSame([2], $result);
    }

    public function test_slice(): void
    {
        $vector = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), [1, 2, 3, 4])
            ->slice(1, 2);

        $this->assertInstanceOf(SubVector::class, $vector);
        $this->assertCount(2, $vector);
    }

    public function test_slice_to_empty(): void
    {
        $vector = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), [1, 2, 3, 4])
            ->slice(1, 0);

        $this->assertInstanceOf(PersistentVector::class, $vector);
        $this->assertCount(0, $vector);
    }

    public function test_slice_without_length(): void
    {
        $vector = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), [1, 2, 3, 4])
            ->slice(1);

        $this->assertInstanceOf(SubVector::class, $vector);
        $this->assertCount(3, $vector);
    }

    public function test_as_transient(): void
    {
        $vector = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), [1, 2, 3, 4])
            ->asTransient();

        $this->assertInstanceOf(TransientVector::class, $vector);
        $this->assertCount(4, $vector);
    }

    public function test_first_on_empty(): void
    {
        $vector = PersistentVector::empty(new ModuloHasher(), new SimpleEqualizer());
        $this->assertNull($vector->first());
    }

    public function test_first_single_element_vector(): void
    {
        $vector = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), [1]);
        $this->assertSame(1, $vector->first());
    }

    public function test_invoke(): void
    {
        /** @var PersistentVector $vector */
        $vector = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), [1, 2, 3, 4]);

        $this->assertSame(2, $vector(1));
    }

    public function test_invoke_with_nil_throws_clear_error(): void
    {
        /** @var PersistentVector $vector */
        $vector = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), [1, 2, 3]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Vector cannot be indexed with nil');

        $vector(null);
    }

    public function test_rest_on_empty_vector(): void
    {
        $vector = PersistentVector::empty(new ModuloHasher(), new SimpleEqualizer());
        $this->assertEquals(
            PersistentVector::empty(new ModuloHasher(), new SimpleEqualizer()),
            $vector->rest(),
        );
    }

    public function test_rest_on_one_element_vector(): void
    {
        $vector = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), [1]);
        $this->assertEquals(
            PersistentVector::empty(new ModuloHasher(), new SimpleEqualizer()),
            $vector->rest(),
        );
    }

    public function test_rest_on_two_element_vector(): void
    {
        $vector = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), [1, 2]);

        $result = [];
        foreach ($vector->rest() as $persistentVector) {
            $result[] = $persistentVector;
        }

        $this->assertSame([2], $result);
    }

    public function test_hash(): void
    {
        $vector = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), [1, 2]);
        $this->assertSame(994, $vector->hash());
    }

    public function test_equals_other_type(): void
    {
        $vector = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), [1, 2]);

        $this->assertFalse($vector->equals([1, 2]));
    }

    public function test_equals_different_length(): void
    {
        $vector1 = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), [1, 2]);
        $vector2 = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), [1, 2, 3]);

        $this->assertFalse($vector1->equals($vector2));
        $this->assertFalse($vector2->equals($vector1));
    }

    public function test_equals_different_values(): void
    {
        $vector1 = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), [1, 2]);
        $vector2 = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), [1, 3]);

        $this->assertFalse($vector1->equals($vector2));
        $this->assertFalse($vector2->equals($vector1));
    }

    public function test_equals_same_values(): void
    {
        $vector1 = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), [1, 2]);
        $vector2 = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), [1, 2]);

        $this->assertTrue($vector1->equals($vector2));
        $this->assertTrue($vector2->equals($vector1));
    }

    public function test_equals_empty_vectors(): void
    {
        $vector1 = PersistentVector::empty(new ModuloHasher(), new SimpleEqualizer());
        $vector2 = PersistentVector::empty(new ModuloHasher(), new SimpleEqualizer());

        $this->assertTrue($vector1->equals($vector2));
        $this->assertTrue($vector2->equals($vector1));
    }

    public function test_equals_identity_short_circuit(): void
    {
        $vector = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), range(0, 100));

        $this->assertTrue($vector->equals($vector));
    }

    #[DataProvider('provideMultiChunkSizes')]
    public function test_equals_multi_chunk_equal_vectors(int $size): void
    {
        $elements = range(0, $size - 1);
        $vector1 = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), $elements);
        $vector2 = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), $elements);

        $this->assertNotSame($vector1, $vector2);
        $this->assertTrue($vector1->equals($vector2));
        $this->assertTrue($vector2->equals($vector1));
    }

    #[DataProvider('provideMultiChunkSizes')]
    public function test_equals_multi_chunk_differs_at_first(int $size): void
    {
        $base = range(0, $size - 1);
        $changed = $base;
        $changed[0] = -1;

        $vector1 = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), $base);
        $vector2 = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), $changed);

        $this->assertFalse($vector1->equals($vector2));
        $this->assertFalse($vector2->equals($vector1));
    }

    #[DataProvider('provideMultiChunkSizes')]
    public function test_equals_multi_chunk_differs_at_middle(int $size): void
    {
        $base = range(0, $size - 1);
        $changed = $base;
        $changed[intdiv($size, 2)] = -1;

        $vector1 = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), $base);
        $vector2 = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), $changed);

        $this->assertFalse($vector1->equals($vector2));
        $this->assertFalse($vector2->equals($vector1));
    }

    #[DataProvider('provideMultiChunkSizes')]
    public function test_equals_multi_chunk_differs_at_last(int $size): void
    {
        $base = range(0, $size - 1);
        $changed = $base;
        $changed[$size - 1] = -1;

        $vector1 = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), $base);
        $vector2 = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), $changed);

        $this->assertFalse($vector1->equals($vector2));
        $this->assertFalse($vector2->equals($vector1));
    }

    public function test_equals_multi_chunk_different_length(): void
    {
        $vector1 = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), range(0, 99));
        $vector2 = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), range(0, 100));

        $this->assertFalse($vector1->equals($vector2));
        $this->assertFalse($vector2->equals($vector1));
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function provideMultiChunkSizes(): iterable
    {
        yield 'single element' => [1];
        yield 'full first chunk' => [31];
        yield 'chunk boundary' => [32];
        yield 'crosses one chunk' => [33];
        yield 'depth two boundary' => [1024];
        yield 'crosses depth two' => [1025];
    }

    public function test_offset_get(): void
    {
        $vector = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), [1, 2]);

        $this->assertSame(1, $vector[0]);
        $this->assertSame(2, $vector[1]);
    }

    public function test_offset_exists(): void
    {
        $vector = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), [1, 2]);

        $this->assertArrayHasKey(0, $vector);
        $this->assertArrayHasKey(1, $vector);
        $this->assertArrayNotHasKey(2, $vector);
    }

    public function test_push(): void
    {
        $vector1 = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), [1, 2]);
        $vector2 = $vector1->push(3);

        $this->assertCount(2, $vector1);
        $this->assertCount(3, $vector2);
        $this->assertSame(3, $vector2->get(2));
    }

    public function test_concat(): void
    {
        $vector1 = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), [1, 2]);
        $vector2 = $vector1->concat([3, 4]);

        $this->assertCount(2, $vector1);
        $this->assertCount(4, $vector2);
        $this->assertSame(3, $vector2->get(2));
        $this->assertSame(4, $vector2->get(3));
    }

    public function test_contains(): void
    {
        $vector = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), [1, 2]);

        $this->assertTrue($vector->contains(0));
        $this->assertTrue($vector->contains(1));
        $this->assertFalse($vector->contains(2));
    }

    /**
     * Regression for the int->float overflow: the rolling `31 * $hash + ...`
     * accumulator used to promote to float once a vector had ~13+ elements,
     * which then threw a TypeError when assigned to the `?int $hashCache`.
     */
    #[DataProvider('provideLargeVectorSizes')]
    public function test_hash_large_vector_returns_stable_int(int $size): void
    {
        $vector = PersistentVector::fromArray(new Hasher(), new Equalizer(), range(0, $size - 1));

        $hash = $vector->hash();

        $this->assertIsInt($hash);
        // Cached: a second call must return the exact same value.
        $this->assertSame($hash, $vector->hash());
    }

    public static function provideLargeVectorSizes(): Iterator
    {
        yield '13 elements' => [13];
        yield '40 elements' => [40];
        yield '1000 elements' => [1000];
    }

    public function test_equal_large_vectors_have_equal_hash(): void
    {
        $left = PersistentVector::fromArray(new Hasher(), new Equalizer(), range(0, 999));
        $right = PersistentVector::fromArray(new Hasher(), new Equalizer(), range(0, 999));

        $this->assertTrue($left->equals($right));
        $this->assertSame($left->hash(), $right->hash());
    }

    public function test_large_vector_works_as_hash_map_key(): void
    {
        $key = PersistentVector::fromArray(new Hasher(), new Equalizer(), range(0, 999));
        $equalKey = PersistentVector::fromArray(new Hasher(), new Equalizer(), range(0, 999));

        $map = PersistentHashMap::empty(new Hasher(), new Equalizer())
            ->put($key, 'value');

        // Lookup with an equal-but-distinct vector instance.
        $this->assertSame('value', $map->find($equalKey));

        // Inserting the equal key must dedup, not grow the map.
        $map = $map->put($equalKey, 'other');
        $this->assertCount(1, $map);
        $this->assertSame('other', $map->find($key));
    }
}
