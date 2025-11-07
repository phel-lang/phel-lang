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

use function array_slice;

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

    public function test_cdr_memoization_prevents_generator_advancement(): void
    {
        // This test verifies the bug fix where calling cdr() multiple times
        // on the same ChunkedSeq instance (when chunk is exhausted) would
        // advance the generator each time, causing chunks to be skipped.

        $generator = (static function (): Generator {
            for ($i = 1; $i <= 100; ++$i) {
                yield $i;
            }
        })();

        // Create a ChunkedSeq with chunk size 1 to force immediate exhaustion
        $chunkedSeq = ChunkedSeq::fromGenerator($this->hasher, $this->equalizer, $generator, 1);

        $this->assertSame(1, $chunkedSeq->first());

        // Call cdr() multiple times on the same instance
        // Without memoization, each call would advance the generator
        $cdr1 = $chunkedSeq->cdr();
        $cdr2 = $chunkedSeq->cdr();
        $cdr3 = $chunkedSeq->cdr();

        // All calls should return equivalent results
        $this->assertSame(2, $cdr1?->first(), 'First cdr() should return 2');
        $this->assertSame(2, $cdr2?->first(), 'Second cdr() should return 2 (not 3!)');
        $this->assertSame(2, $cdr3?->first(), 'Third cdr() should return 2 (not 4!)');

        // Verify the rest of the sequence is consistent
        $this->assertSame([2, 3, 4, 5], array_slice($cdr1?->toArray() ?? [], 0, 4));
        $this->assertSame([2, 3, 4, 5], array_slice($cdr2?->toArray() ?? [], 0, 4));
        $this->assertSame([2, 3, 4, 5], array_slice($cdr3?->toArray() ?? [], 0, 4));
    }

    public function test_iterator_memoization_prevents_duplicate_realization(): void
    {
        // This test verifies that creating multiple iterators on the same
        // ChunkedSeq instance doesn't cause the generator to be invoked
        // multiple times (which would skip chunks).

        $realizationCount = 0;
        $generator = (static function () use (&$realizationCount): Generator {
            for ($i = 1; $i <= 10; ++$i) {
                ++$realizationCount;
                yield $i;
            }
        })();

        $chunkedSeq = ChunkedSeq::fromGenerator($this->hasher, $this->equalizer, $generator, 3);

        // First iteration
        $result1 = [];
        foreach ($chunkedSeq as $value) {
            $result1[] = $value;
        }

        $countAfterFirst = $realizationCount;

        // Second iteration on the SAME instance
        $result2 = [];
        foreach ($chunkedSeq as $value) {
            $result2[] = $value;
        }

        $countAfterSecond = $realizationCount;

        // Both iterations should produce the same results
        $this->assertSame([1, 2, 3, 4, 5, 6, 7, 8, 9, 10], $result1);
        $this->assertSame([1, 2, 3, 4, 5, 6, 7, 8, 9, 10], $result2);

        // The generator should not be advanced again on the second iteration
        // (memoization should prevent re-invocation)
        $this->assertSame($countAfterFirst, $countAfterSecond, 'Generator should not be invoked again');
    }
}
