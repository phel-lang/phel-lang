<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang\Collections\Vector;

use Phel\Lang\Collections\Exceptions\IndexOutOfBoundsException;
use Phel\Lang\Collections\Vector\TransientVector;
use PhelTest\Unit\Lang\Collections\ModuloHasher;
use PhelTest\Unit\Lang\Collections\SimpleEqualizer;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class TransientVectorTest extends TestCase
{
    public function test_append_to_tail(): void
    {
        $vEmpty = TransientVector::empty(new ModuloHasher(), new SimpleEqualizer());
        $v1 = $vEmpty->append('a');

        $this->assertEquals(1, $vEmpty->count());
        $this->assertEquals(1, $v1->count());
        $this->assertEquals('a', $v1->get(0));
    }

    public function test_append_tail_is_full(): void
    {
        $v1 = TransientVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), range(0, 31));
        $v2 = $v1->append(32);

        $this->assertEquals(33, $v1->count());
        $this->assertEquals(33, $v2->count());
        $this->assertEquals(32, $v2->get(32));
    }

    public function test_append_overflow_root(): void
    {
        $initialLength = 32 + (32 * 32) - 1;
        $v1 = TransientVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), range(0, $initialLength));
        $v2 = $v1->append(1056);

        $this->assertEquals(1057, $v1->count());
        $this->assertEquals(1057, $v2->count());
        $this->assertEquals(1056, $v2->get(1056));
    }

    public function test_append_tail_is_full_second_level(): void
    {
        $initialLength = 32 + (32 * 32) + 32 - 1;
        $v1 = TransientVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), range(0, $initialLength));
        $v2 = $v1->append($initialLength + 1);

        $this->assertEquals($initialLength + 2, $v1->count());
        $this->assertEquals($initialLength + 2, $v2->count());
        $this->assertEquals($initialLength + 1, $v2->get($initialLength + 1));
    }

    public function test_append_tail_is_full_third_level(): void
    {
        $initialLength = 32 + (32 * 32) + (32 * 32 * 32) - 1;
        $v1 = TransientVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), range(0, $initialLength));
        $v2 = $v1->append($initialLength + 1);

        $this->assertEquals($initialLength + 2, $v1->count());
        $this->assertEquals($initialLength + 2, $v2->count());
        $this->assertEquals($initialLength + 1, $v2->get($initialLength + 1));
    }

    public function test_update_out_of_range(): void
    {
        $this->expectException(IndexOutOfBoundsException::class);
        $v = TransientVector::empty(new ModuloHasher(), new SimpleEqualizer());

        $v->update(1, 10);
    }

    public function test_update_append(): void
    {
        $vEmpty = TransientVector::empty(new ModuloHasher(), new SimpleEqualizer());
        $v1 = $vEmpty->update(0, 10);

        $this->assertEquals(1, $vEmpty->count());
        $this->assertEquals(1, $v1->count());
        $this->assertEquals(10, $vEmpty->get(0));
        $this->assertEquals(10, $v1->get(0));
    }

    public function test_update_in_tail(): void
    {
        $v1 = TransientVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), [10]);
        $v2 = $v1->update(0, 20);

        $this->assertEquals(1, $v1->count());
        $this->assertEquals(1, $v2->count());
        $this->assertEquals(20, $v1->get(0));
        $this->assertEquals(20, $v2->get(0));
    }

    public function test_update_in_level_tree(): void
    {
        $v1 = TransientVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), range(0, 32));
        $v2 = $v1->update(0, 20);

        $this->assertEquals(33, $v1->count());
        $this->assertEquals(33, $v2->count());
        $this->assertEquals(20, $v1->get(0));
        $this->assertEquals(20, $v2->get(0));
    }

    public function test_get_out_of_range(): void
    {
        $this->expectException(IndexOutOfBoundsException::class);
        $vEmpty = TransientVector::empty(new ModuloHasher(), new SimpleEqualizer());

        $vEmpty->get(0);
    }

    public function test_pop_on_empty_vector(): void
    {
        $this->expectException(RuntimeException::class);
        $vEmpty = TransientVector::empty(new ModuloHasher(), new SimpleEqualizer());

        $vEmpty->pop();
    }

    public function test_pop_on_one_element_vector(): void
    {
        $v1 = TransientVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), [1]);
        $vEmpty = $v1->pop();

        $this->assertEquals(0, $v1->count());
        $this->assertEquals(0, $vEmpty->count());
    }

    public function test_pop_from_tail(): void
    {
        $v1 = TransientVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), [1, 2]);
        $v2 = $v1->pop();

        $this->assertEquals(1, $v1->count());
        $this->assertEquals(1, $v2->count());
        $this->assertEquals(1, $v2->get(0));
    }

    public function test_pop_from_tree_level_one(): void
    {
        $v1 = TransientVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), range(0, 32));
        $v2 = $v1->pop();

        $this->assertEquals(32, $v1->count());
        $this->assertEquals(32, $v2->count());
    }

    public function test_pop_from_tree_level_two(): void
    {
        $length = 32 + (32 * 32);
        $v1 = TransientVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), range(0, $length));
        $v2 = $v1->pop();

        $this->assertEquals($length, $v1->count());
        $this->assertEquals($length, $v2->count());
    }

    public function test_pop_from_tree_level_two2(): void
    {
        $length = (32 * 32);
        $v1 = TransientVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), range(0, $length));
        $v2 = $v1->pop();

        $this->assertEquals($length, $v1->count());
        $this->assertEquals($length, $v2->count());
    }

    public function test_pop_from_tree_level_three(): void
    {
        $length = (32 * 32) + (32 * 32 * 31) + 32;
        $v1 = TransientVector::fromArray(new ModuloHasher(), new SimpleEqualizer(), range(0, $length));
        $v2 = $v1->pop();

        $this->assertEquals($length, $v1->count());
        $this->assertEquals($length, $v2->count());
    }
}
