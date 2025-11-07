<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang\Collections\LazySeq;

use Generator;
use Phel\Lang\Collections\LazySeq\ChunkedSeq;
use Phel\Lang\Collections\LazySeq\LazySeqConfig;
use Phel\Lang\Collections\LazySeq\LazySeqInterface;
use PhelTest\Unit\Lang\Collections\ModuloHasher;
use PhelTest\Unit\Lang\Collections\SimpleEqualizer;
use PHPUnit\Framework\TestCase;

final class ChunkedSeqTest extends TestCase
{
    private ModuloHasher $hasher;

    private SimpleEqualizer $equalizer;

    protected function setUp(): void
    {
        $this->hasher = new ModuloHasher();
        $this->equalizer = new SimpleEqualizer();
    }

    public function test_creates_chunked_seq_from_array(): void
    {
        $array = range(0, 99);
        $chunkedSeq = ChunkedSeq::fromArray($this->hasher, $this->equalizer, $array, LazySeqConfig::CHUNK_SIZE);

        $this->assertSame(0, $chunkedSeq->first());
        $this->assertSame($array, $chunkedSeq->toArray());
    }

    public function test_creates_chunked_seq_with_custom_chunk_size(): void
    {
        $array = range(0, 99);
        $chunkedSeq = ChunkedSeq::fromArray($this->hasher, $this->equalizer, $array, 10);

        $this->assertSame(0, $chunkedSeq->first());
        $this->assertCount(100, $chunkedSeq->toArray());
    }

    public function test_creates_from_generator(): void
    {
        $generator = (static function (): Generator {
            for ($i = 0; $i < 100; ++$i) {
                yield $i;
            }
        })();

        $chunkedSeq = ChunkedSeq::fromGenerator($this->hasher, $this->equalizer, $generator, 32);

        $this->assertSame(0, $chunkedSeq->first());
        $this->assertCount(100, $chunkedSeq->toArray());
    }

    public function test_first_returns_first_element_of_chunk(): void
    {
        $chunkedSeq = ChunkedSeq::fromArray($this->hasher, $this->equalizer, [1, 2, 3, 4, 5], 2);

        $this->assertSame(1, $chunkedSeq->first());
    }

    public function test_rest_returns_remaining_in_chunk(): void
    {
        $chunkedSeq = ChunkedSeq::fromArray($this->hasher, $this->equalizer, [1, 2, 3, 4, 5], 2);

        $rest = $chunkedSeq->rest();
        $this->assertSame(2, $rest->first());
    }

    public function test_cdr_returns_next_element_in_chunk(): void
    {
        $chunkedSeq = ChunkedSeq::fromArray($this->hasher, $this->equalizer, [1, 2, 3], 2);

        $cdr = $chunkedSeq->cdr();
        $this->assertInstanceOf(LazySeqInterface::class, $cdr);
        $this->assertSame(2, $cdr->first());
    }

    public function test_cdr_moves_to_next_chunk_when_current_exhausted(): void
    {
        // Create sequence with multiple chunks
        $array = range(1, 65); // Will create at least 3 chunks with size 32
        $chunkedSeq = ChunkedSeq::fromArray($this->hasher, $this->equalizer, $array, 32);

        // Move through first chunk
        $current = $chunkedSeq;
        for ($i = 0; $i < 31; ++$i) {
            $current = $current->rest();
        }

        $this->assertSame(32, $current->first(), 'Should be at element 32');

        // Move to next element (which should be in next chunk)
        $next = $current->rest();
        $this->assertSame(33, $next->first(), 'Should move to next chunk');
    }

    public function test_cons_prepends_to_chunk(): void
    {
        $chunkedSeq = ChunkedSeq::fromArray($this->hasher, $this->equalizer, [2, 3, 4], 32);

        $consed = $chunkedSeq->cons(1);

        $this->assertSame(1, $consed->first());
        $this->assertSame([1, 2, 3, 4], $consed->toArray());
    }

    public function test_to_array_realizes_all_chunks(): void
    {
        $array = range(0, 99);
        $chunkedSeq = ChunkedSeq::fromArray($this->hasher, $this->equalizer, $array, 10);

        $result = $chunkedSeq->toArray();

        $this->assertSame($array, $result);
    }

    public function test_count_realizes_all_chunks(): void
    {
        $chunkedSeq = ChunkedSeq::fromArray($this->hasher, $this->equalizer, range(0, 99), 10);

        $this->assertSame(100, $chunkedSeq->count());
    }

    public function test_iterator_traverses_all_chunks(): void
    {
        $array = range(0, 99);
        $chunkedSeq = ChunkedSeq::fromArray($this->hasher, $this->equalizer, $array, 10);

        $result = [];
        foreach ($chunkedSeq as $value) {
            $result[] = $value;
        }

        $this->assertSame($array, $result);
    }

    public function test_empty_array_returns_null(): void
    {
        $chunkedSeq = ChunkedSeq::fromArray($this->hasher, $this->equalizer, [], 32);

        $this->assertNotInstanceOf(ChunkedSeq::class, $chunkedSeq);
    }

    public function test_is_always_realized_at_least_first_chunk(): void
    {
        $chunkedSeq = ChunkedSeq::fromArray($this->hasher, $this->equalizer, range(0, 99), 32);

        $this->assertTrue($chunkedSeq->isRealized(), 'ChunkedSeq is always at least partially realized');
    }

    public function test_chunked_seq_with_infinite_generator(): void
    {
        $generator = (static function (): Generator {
            $i = 0;
            while (true) {
                yield $i++;
            }
        })();

        $chunkedSeq = ChunkedSeq::fromGenerator($this->hasher, $this->equalizer, $generator, 32);

        // Should realize first chunk without infinite loop
        $this->assertSame(0, $chunkedSeq->first());

        // Should be able to access elements within first chunk
        $rest = $chunkedSeq->rest();
        for ($i = 0; $i < 30; ++$i) {
            $rest = $rest->rest();
        }

        $this->assertSame(31, $rest->first());
    }

    public function test_performance_chunks_realize_in_batches(): void
    {
        $realizationCount = 0;
        $chunkSize = 10;

        // Create a generator that tracks realizations
        $generator = (static function () use (&$realizationCount): Generator {
            for ($i = 0; $i < 100; ++$i) {
                ++$realizationCount;
                yield $i;
            }
        })();

        $chunkedSeq = ChunkedSeq::fromGenerator($this->hasher, $this->equalizer, $generator, $chunkSize);

        // Accessing first element should realize first chunk
        $chunkedSeq->first();
        // Note: fromGenerator needs to call valid() which may advance the generator by 1
        $this->assertGreaterThanOrEqual($chunkSize, $realizationCount, 'First chunk should be realized');
        $this->assertLessThanOrEqual($chunkSize + 1, $realizationCount, 'Should not realize much more than first chunk');

        // Accessing element in second chunk
        $current = $chunkedSeq;
        for ($i = 0; $i < $chunkSize; ++$i) {
            $current = $current->rest();
        }

        $current->first();

        $this->assertGreaterThanOrEqual($chunkSize * 2, $realizationCount, 'Second chunk should be realized');
        $this->assertLessThanOrEqual($chunkSize * 2 + 2, $realizationCount, 'Should not realize much more than two chunks');
    }
}
