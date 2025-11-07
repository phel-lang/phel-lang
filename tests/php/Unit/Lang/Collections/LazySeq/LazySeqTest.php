<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang\Collections\LazySeq;

use Generator;
use Phel\Lang\Collections\LazySeq\LazySeq;
use Phel\Lang\Collections\LazySeq\LazySeqInterface;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use PhelTest\Unit\Lang\Collections\ModuloHasher;
use PhelTest\Unit\Lang\Collections\SimpleEqualizer;
use PHPUnit\Framework\TestCase;

final class LazySeqTest extends TestCase
{
    private ModuloHasher $hasher;

    private SimpleEqualizer $equalizer;

    protected function setUp(): void
    {
        $this->hasher = new ModuloHasher();
        $this->equalizer = new SimpleEqualizer();
    }

    public function test_creates_lazy_seq_from_function(): void
    {
        $callCount = 0;
        $lazySeq = new LazySeq(
            $this->hasher,
            $this->equalizer,
            static function () use (&$callCount): null {
                ++$callCount;
                return null;
            },
        );

        $this->assertFalse($lazySeq->isRealized());
        $this->assertSame(0, $callCount, 'Function should not be called until realization');

        $lazySeq->first();
        $this->assertTrue($lazySeq->isRealized());
        $this->assertSame(1, $callCount, 'Function should be called once');
    }

    public function test_caches_realized_values(): void
    {
        $callCount = 0;
        $lazySeq = new LazySeq(
            $this->hasher,
            $this->equalizer,
            function () use (&$callCount): ?LazySeq {
                ++$callCount;
                return LazySeq::fromArray($this->hasher, $this->equalizer, [1, 2, 3]);
            },
        );

        $first1 = $lazySeq->first();
        $first2 = $lazySeq->first();

        $this->assertSame($first1, $first2);
        $this->assertSame(1, $callCount, 'Function should only be called once');
    }

    public function test_creates_from_array(): void
    {
        $lazySeq = LazySeq::fromArray($this->hasher, $this->equalizer, [1, 2, 3, 4, 5]);

        $this->assertSame(1, $lazySeq->first());
        $this->assertSame([1, 2, 3, 4, 5], $lazySeq->toArray());
    }

    public function test_creates_from_empty_array(): void
    {
        $lazySeq = LazySeq::fromArray($this->hasher, $this->equalizer, []);

        $this->assertNotInstanceOf(LazySeq::class, $lazySeq);
    }

    public function test_creates_from_generator(): void
    {
        $generator = (static function (): Generator {
            yield 1;
            yield 2;
            yield 3;
        })();

        $lazySeq = LazySeq::fromGenerator($this->hasher, $this->equalizer, $generator);

        $this->assertSame(1, $lazySeq->first());
        $this->assertSame([1, 2, 3], $lazySeq->toArray());
    }

    public function test_first_returns_first_element(): void
    {
        $lazySeq = LazySeq::fromArray($this->hasher, $this->equalizer, [10, 20, 30]);

        $this->assertSame(10, $lazySeq->first());
    }

    public function test_first_returns_null_for_empty_sequence(): void
    {
        $lazySeq = new LazySeq(
            $this->hasher,
            $this->equalizer,
            static fn (): null => null,
        );

        $this->assertNull($lazySeq->first());
    }

    public function test_rest_returns_lazy_sequence(): void
    {
        $lazySeq = LazySeq::fromArray($this->hasher, $this->equalizer, [1, 2, 3]);

        $rest = $lazySeq->rest();

        $this->assertInstanceOf(LazySeq::class, $rest);
        $this->assertSame(2, $rest->first());
    }

    public function test_cdr_returns_lazy_sequence_or_null(): void
    {
        $lazySeq = LazySeq::fromArray($this->hasher, $this->equalizer, [1]);

        $cdr = $lazySeq->cdr();

        $this->assertNotInstanceOf(LazySeqInterface::class, $cdr);
    }

    public function test_cons_prepends_element(): void
    {
        $lazySeq = LazySeq::fromArray($this->hasher, $this->equalizer, [2, 3]);

        $consed = $lazySeq->cons(1);

        $this->assertSame(1, $consed->first());
        $this->assertSame([1, 2, 3], $consed->toArray());
    }

    public function test_to_array_realizes_entire_sequence(): void
    {
        $lazySeq = LazySeq::fromArray($this->hasher, $this->equalizer, [1, 2, 3, 4, 5]);

        $array = $lazySeq->toArray();

        $this->assertSame([1, 2, 3, 4, 5], $array);
    }

    public function test_count_realizes_entire_sequence(): void
    {
        $lazySeq = LazySeq::fromArray($this->hasher, $this->equalizer, [1, 2, 3, 4, 5]);

        $count = $lazySeq->count();

        $this->assertSame(5, $count);
    }

    public function test_iterator_allows_foreach(): void
    {
        $lazySeq = LazySeq::fromArray($this->hasher, $this->equalizer, [1, 2, 3]);

        $result = [];
        foreach ($lazySeq as $value) {
            $result[] = $value;
        }

        $this->assertSame([1, 2, 3], $result);
    }

    public function test_lazy_evaluation_with_infinite_generator(): void
    {
        $generator = (static function (): Generator {
            $i = 0;
            while (true) {
                yield $i++;
            }
        })();

        $lazySeq = LazySeq::fromGenerator($this->hasher, $this->equalizer, $generator);

        // Should be able to get first element without infinite loop
        $this->assertSame(0, $lazySeq->first());

        // Should be able to get rest without realizing everything
        $rest = $lazySeq->rest();
        $this->assertSame(1, $rest->first());
    }

    public function test_metadata_support(): void
    {
        $lazySeq = LazySeq::fromArray($this->hasher, $this->equalizer, [1, 2, 3]);

        $this->assertNotInstanceOf(PersistentMapInterface::class, $lazySeq->getMeta());

        // WithMeta should be tested if metadata implementation is needed
    }

    public function test_chaining_operations(): void
    {
        $lazySeq = LazySeq::fromArray($this->hasher, $this->equalizer, [1, 2, 3, 4, 5]);

        $result = $lazySeq->rest()->rest()->first();

        $this->assertSame(3, $result);
    }

    public function test_realizes_lazily_on_demand(): void
    {
        $values = [1, 2, 3, 4, 5];
        $accessCount = 0;

        $lazySeq = new LazySeq(
            $this->hasher,
            $this->equalizer,
            function () use ($values, &$accessCount): ?LazySeq {
                ++$accessCount;
                return LazySeq::fromArray($this->hasher, $this->equalizer, $values);
            },
        );

        $this->assertSame(0, $accessCount, 'Should not realize until accessed');

        $lazySeq->first();
        $this->assertSame(1, $accessCount, 'Should realize on first access');

        $lazySeq->first();
        $this->assertSame(1, $accessCount, 'Should use cached value');
    }
}
