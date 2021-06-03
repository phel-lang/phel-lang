<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang\Collections\Vector;

use Phel\Lang\Collections\Exceptions\IndexOutOfBoundsException;
use Phel\Lang\Collections\Vector\PersistentVector;
use Phel\Lang\Collections\Vector\RangeIterator;
use Phel\Lang\Collections\Vector\SubVector;
use Phel\Lang\Collections\Vector\TransientVector;
use Phel\Lang\TypeFactory;
use PhelTest\Unit\Lang\Collections\ModuloHasher;
use PhelTest\Unit\Lang\Collections\SimpleEqualizer;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PersistentVectorTest extends TestCase
{
    public function test_append_to_tail(): void
    {
        $vEmpty = PersistentVector::empty(new ModuloHasher(), new SimpleEqualizer());
        $v1 = $vEmpty->append('a');

        $this->assertEquals(0, $vEmpty->count());
        $this->assertEquals(1, $v1->count());
        $this->assertEquals('a', $v1->get(0));
    }

    public function test_append_tail_is_full(): void
    {
        $v1 = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), range(0, 31));
        $v2 = $v1->append(32);

        $this->assertEquals(32, $v1->count());
        $this->assertEquals(33, $v2->count());
        $this->assertEquals(32, $v2->get(32));
    }

    public function test_append_overflow_root(): void
    {
        $initialLength = 32 + (32 * 32) - 1;
        $v1 = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), range(0, $initialLength));
        $v2 = $v1->append(1056);

        $this->assertEquals(1056, $v1->count());
        $this->assertEquals(1057, $v2->count());
        $this->assertEquals(1056, $v2->get(1056));
    }

    public function test_append_tail_is_full_second_level(): void
    {
        $initialLength = 32 + (32 * 32) + 32 - 1;
        $v1 = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), range(0, $initialLength));
        $v2 = $v1->append($initialLength + 1);

        $this->assertEquals($initialLength + 1, $v1->count());
        $this->assertEquals($initialLength + 2, $v2->count());
        $this->assertEquals($initialLength + 1, $v2->get($initialLength + 1));
    }

    public function test_append_tail_is_full_third_level(): void
    {
        $initialLength = 32 + (32 * 32) + (32 * 32 * 32) - 1;
        $v1 = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), range(0, $initialLength));
        $v2 = $v1->append($initialLength + 1);

        $this->assertEquals($initialLength + 1, $v1->count());
        $this->assertEquals($initialLength + 2, $v2->count());
        $this->assertEquals($initialLength + 1, $v2->get($initialLength + 1));
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

        $this->assertEquals(0, $vEmpty->count());
        $this->assertEquals(1, $v1->count());
        $this->assertEquals(10, $v1->get(0));
    }

    public function test_update_in_tail(): void
    {
        $v1 = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), [10]);
        $v2 = $v1->update(0, 20);

        $this->assertEquals(1, $v1->count());
        $this->assertEquals(1, $v2->count());
        $this->assertEquals(10, $v1->get(0));
        $this->assertEquals(20, $v2->get(0));
    }

    public function test_update_in_level_tree(): void
    {
        $v1 = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), range(0, 32));
        $v2 = $v1->update(0, 20);

        $this->assertEquals(33, $v1->count());
        $this->assertEquals(33, $v2->count());
        $this->assertEquals(0, $v1->get(0));
        $this->assertEquals(20, $v2->get(0));
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

        $this->assertEquals(1, $v1->count());
        $this->assertEquals(0, $vEmpty->count());
    }

    public function test_pop_from_tail(): void
    {
        $v1 = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), [1, 2]);
        $v2 = $v1->pop();

        $this->assertEquals(2, $v1->count());
        $this->assertEquals(1, $v2->count());
        $this->assertEquals(1, $v2->get(0));
    }

    public function test_pop_from_tree_level_one(): void
    {
        $v1 = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), range(0, 32));
        $v2 = $v1->pop();

        $this->assertEquals(33, $v1->count());
        $this->assertEquals(32, $v2->count());
    }

    public function test_pop_from_tree_level_two(): void
    {
        $length = 32 + (32 * 32);
        $v1 = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), range(0, $length));
        $v2 = $v1->pop();

        $this->assertEquals($length + 1, $v1->count());
        $this->assertEquals($length, $v2->count());
    }

    public function test_pop_from_tree_level_two2(): void
    {
        $length = (32 * 32);
        $v1 = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), range(0, $length));
        $v2 = $v1->pop();

        $this->assertEquals($length + 1, $v1->count());
        $this->assertEquals($length, $v2->count());
    }

    public function test_pop_from_tree_level_three(): void
    {
        $length = (32 * 32) + (32 * 32 * 31) + 32;
        $v1 = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), range(0, $length));
        $v2 = $v1->pop();

        $this->assertEquals($length + 1, $v1->count());
        $this->assertEquals($length, $v2->count());
    }

    public function test_to_array_tail(): void
    {
        $arr = [1, 2, 3];
        $this->assertEquals($arr, PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), $arr)->toArray());
    }

    public function test_to_array_level_one(): void
    {
        $arr = range(0, 32);
        $this->assertEquals($arr, PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), $arr)->toArray());
    }

    public function test_get_iterator_on_empty_vector(): void
    {
        $v = PersistentVector::empty(new ModuloHasher(), new SimpleEqualizer());
        $result = [];
        foreach ($v as $x) {
            $result[] = $x;
        }

        $this->assertEquals([], $result);
    }

    public function test_get_iterator_on_tail_only_vector(): void
    {
        $v = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), [1, 2]);
        $result = [];
        foreach ($v as $x) {
            $result[] = $x;
        }

        $this->assertEquals([1, 2], $result);
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

        $this->assertEquals(range(0, 32), $result);
        $this->assertEquals(range(0, 32), $indices);
    }

    public function test_get_range_iterator(): void
    {
        $this->assertInstanceOf(RangeIterator::class, PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), [1, 2, 3])->getRangeIterator(0, 2));
    }

    public function test_add_meta_data(): void
    {
        $meta = TypeFactory::getInstance()->emptyPersistentMap();
        $vector = PersistentVector::empty(new ModuloHasher(), new SimpleEqualizer());
        $vectorWithMeta = $vector->withMeta($meta);

        $this->assertEquals(null, $vector->getMeta());
        $this->assertEquals($meta, $vectorWithMeta->getMeta());
    }

    public function test_cdr_on_empty_vector(): void
    {
        $vector = PersistentVector::empty(new ModuloHasher(), new SimpleEqualizer());
        $this->assertNull($vector->cdr());
    }

    public function test_cdr_on_one_element_vector(): void
    {
        $vector = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), [1]);
        $this->assertNull($vector->cdr());
    }

    public function test_cdr_on_two_element_vector(): void
    {
        $vector = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), [1, 2]);

        $result = [];
        foreach ($vector->cdr() as $x) {
            $result[] = $x;
        }
        $this->assertEquals([2], $result);
    }

    public function test_slice(): void
    {
        $vector = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), [1, 2, 3, 4])
            ->slice(1, 2);

        $this->assertInstanceOf(SubVector::class, $vector);
        $this->assertEquals(2, count($vector));
    }

    public function test_slice_to_empty(): void
    {
        $vector = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), [1, 2, 3, 4])
            ->slice(1, 0);

        $this->assertInstanceOf(PersistentVector::class, $vector);
        $this->assertEquals(0, count($vector));
    }

    public function test_slice_without_length(): void
    {
        $vector = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), [1, 2, 3, 4])
            ->slice(1);

        $this->assertInstanceOf(SubVector::class, $vector);
        $this->assertEquals(3, count($vector));
    }

    public function test_as_transient(): void
    {
        $vector = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), [1, 2, 3, 4])
            ->asTransient();

        $this->assertInstanceOf(TransientVector::class, $vector);
        $this->assertEquals(4, count($vector));
    }

    public function test_first_on_empty(): void
    {
        $vector = PersistentVector::empty(new ModuloHasher(), new SimpleEqualizer());
        $this->assertNull($vector->first());
    }

    public function test_first_single_element_vector(): void
    {
        $vector = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), [1]);
        $this->assertEquals(1, $vector->first());
    }

    public function test_invoke(): void
    {
        $vector = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), [1, 2, 3, 4]);

        $this->assertEquals(2, $vector(1));
    }

    public function test_rest_on_empty_vector(): void
    {
        $vector = PersistentVector::empty(new ModuloHasher(), new SimpleEqualizer());
        $this->assertEquals(
            PersistentVector::empty(new ModuloHasher(), new SimpleEqualizer()),
            $vector->rest()
        );
    }

    public function test_rest_on_one_element_vector(): void
    {
        $vector = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), [1]);
        $this->assertEquals(
            PersistentVector::empty(new ModuloHasher(), new SimpleEqualizer()),
            $vector->rest()
        );
    }

    public function test_rest_on_two_element_vector(): void
    {
        $vector = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), [1, 2]);

        $result = [];
        foreach ($vector->rest() as $x) {
            $result[] = $x;
        }
        $this->assertEquals([2], $result);
    }

    public function test_hash(): void
    {
        $vector = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), [1, 2]);
        $this->assertEquals(994, $vector->hash());
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

    public function test_offset_get(): void
    {
        $vector = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), [1, 2]);

        $this->assertEquals(1, $vector[0]);
        $this->assertEquals(2, $vector[1]);
    }

    public function test_offset_exists(): void
    {
        $vector = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), [1, 2]);

        $this->assertTrue(isset($vector[0]));
        $this->assertTrue(isset($vector[1]));
        $this->assertFalse(isset($vector[2]));
    }

    public function test_push(): void
    {
        $vector1 = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), [1, 2]);
        $vector2 = $vector1->push(3);

        $this->assertEquals(2, count($vector1));
        $this->assertEquals(3, count($vector2));
        $this->assertEquals(3, $vector2->get(2));
    }

    public function test_concat(): void
    {
        $vector1 = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), [1, 2]);
        $vector2 = $vector1->concat([3, 4]);

        $this->assertEquals(2, count($vector1));
        $this->assertEquals(4, count($vector2));
        $this->assertEquals(3, $vector2->get(2));
        $this->assertEquals(4, $vector2->get(3));
    }

    public function test_contains(): void
    {
        $vector = PersistentVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), [1, 2]);

        $this->assertTrue($vector->contains(0));
        $this->assertTrue($vector->contains(1));
        $this->assertFalse($vector->contains(2));
    }
}
