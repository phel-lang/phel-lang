<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang\Collections\LazySeq;

use Phel\Lang\Collections\Exceptions\IndexOutOfBoundsException;
use Phel\Lang\Collections\LazySeq\Chunk;
use PHPUnit\Framework\TestCase;

final class ChunkTest extends TestCase
{
    public function test_creates_chunk_with_values(): void
    {
        $chunk = new Chunk([1, 2, 3, 4, 5]);

        $this->assertSame(5, $chunk->count());
        $this->assertSame(1, $chunk->first());
    }

    public function test_get_returns_value_at_index(): void
    {
        $chunk = new Chunk([10, 20, 30, 40]);

        $this->assertSame(10, $chunk->get(0));
        $this->assertSame(20, $chunk->get(1));
        $this->assertSame(30, $chunk->get(2));
        $this->assertSame(40, $chunk->get(3));
    }

    public function test_get_throws_exception_for_invalid_index(): void
    {
        $this->expectException(IndexOutOfBoundsException::class);

        $chunk = new Chunk([1, 2, 3]);
        $chunk->get(10);
    }

    public function test_count_returns_number_of_elements(): void
    {
        $chunk = new Chunk([1, 2, 3, 4, 5]);

        $this->assertSame(5, $chunk->count());
    }

    public function test_drop_returns_new_chunk_with_offset(): void
    {
        $chunk = new Chunk([1, 2, 3, 4, 5]);

        $dropped = $chunk->drop(2);

        $this->assertSame(3, $dropped->count());
        $this->assertSame(3, $dropped->first());
        $this->assertSame(3, $dropped->get(0));
    }

    public function test_drop_more_than_available_returns_empty_chunk(): void
    {
        $chunk = new Chunk([1, 2, 3]);

        $dropped = $chunk->drop(10);

        $this->assertSame(0, $dropped->count());
    }

    public function test_first_returns_first_element(): void
    {
        $chunk = new Chunk([10, 20, 30]);

        $this->assertSame(10, $chunk->first());
    }

    public function test_first_returns_null_for_empty_chunk(): void
    {
        $chunk = new Chunk([]);

        $this->assertNull($chunk->first());
    }

    public function test_to_array_converts_to_php_array(): void
    {
        $chunk = new Chunk([1, 2, 3, 4, 5]);

        $this->assertSame([1, 2, 3, 4, 5], $chunk->toArray());
    }

    public function test_to_array_respects_offset(): void
    {
        $chunk = new Chunk([1, 2, 3, 4, 5], 2);

        $this->assertSame([3, 4, 5], $chunk->toArray());
    }

    public function test_chunk_with_offset(): void
    {
        $chunk = new Chunk([10, 20, 30, 40, 50], 2);

        $this->assertSame(3, $chunk->count());
        $this->assertSame(30, $chunk->first());
        $this->assertSame(30, $chunk->get(0));
        $this->assertSame(40, $chunk->get(1));
    }

    public function test_drop_on_chunk_with_offset(): void
    {
        $chunk = new Chunk([10, 20, 30, 40, 50], 1);
        $dropped = $chunk->drop(2);

        $this->assertSame(2, $dropped->count());
        $this->assertSame(40, $dropped->first());
    }

    public function test_empty_chunk(): void
    {
        $chunk = new Chunk([]);

        $this->assertSame(0, $chunk->count());
        $this->assertNull($chunk->first());
        $this->assertSame([], $chunk->toArray());
    }
}
