<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang\Collections\Queue;

use Phel;
use Phel\Lang\Collections\Queue\PersistentQueue;
use Phel\Lang\Equalizer;
use Phel\Lang\Hasher;
use PHPUnit\Framework\TestCase;
use UnderflowException;

final class PersistentQueueTest extends TestCase
{
    private Hasher $hasher;

    private Equalizer $equalizer;

    protected function setUp(): void
    {
        $this->hasher = new Hasher();
        $this->equalizer = new Equalizer();
    }

    public function test_empty_queue_has_count_zero(): void
    {
        $q = PersistentQueue::empty($this->hasher, $this->equalizer);

        self::assertCount(0, $q);
        self::assertNull($q->first());
        self::assertNull($q->cdr());
    }

    public function test_push_appends_to_back(): void
    {
        $q = PersistentQueue::empty($this->hasher, $this->equalizer)
            ->push(1)
            ->push(2)
            ->push(3);

        self::assertCount(3, $q);
        self::assertSame(1, $q->first());
    }

    public function test_pop_removes_front_in_fifo_order(): void
    {
        $q = PersistentQueue::fromArray($this->hasher, $this->equalizer, [1, 2, 3, 4]);

        $values = [];
        while ($q->count() > 0) {
            $values[] = $q->first();
            $q = $q->pop();
        }

        self::assertSame([1, 2, 3, 4], $values);
    }

    public function test_pop_on_empty_throws(): void
    {
        $this->expectException(UnderflowException::class);
        PersistentQueue::empty($this->hasher, $this->equalizer)->pop();
    }

    public function test_pop_handles_rear_to_front_rebalance(): void
    {
        $q = PersistentQueue::fromArray($this->hasher, $this->equalizer, [1, 2, 3, 4, 5]);
        $q = $q->pop()->pop();

        self::assertSame(3, $q->first());
        self::assertCount(3, $q);
    }

    public function test_cons_is_alias_for_push(): void
    {
        $q = PersistentQueue::empty($this->hasher, $this->equalizer)
            ->cons('a')
            ->cons('b');

        self::assertSame('a', $q->first());
        self::assertCount(2, $q);
    }

    public function test_iteration_yields_values_in_fifo_order(): void
    {
        $q = PersistentQueue::fromArray($this->hasher, $this->equalizer, [10, 20, 30, 40]);

        self::assertSame([10, 20, 30, 40], iterator_to_array($q, false));
    }

    public function test_equality_by_element_sequence(): void
    {
        $a = PersistentQueue::fromArray($this->hasher, $this->equalizer, [1, 2, 3]);
        $b = PersistentQueue::fromArray($this->hasher, $this->equalizer, [1, 2, 3]);
        $c = PersistentQueue::fromArray($this->hasher, $this->equalizer, [1, 2, 4]);

        self::assertTrue($a->equals($b));
        self::assertSame($a->hash(), $b->hash());
        self::assertFalse($a->equals($c));
    }

    public function test_equality_after_push_pop_matches_initial_construction(): void
    {
        $a = PersistentQueue::fromArray($this->hasher, $this->equalizer, [1, 2, 3, 4]);
        $b = PersistentQueue::empty($this->hasher, $this->equalizer)
            ->push(0)
            ->push(1)
            ->push(2)
            ->push(3)
            ->push(4)
            ->pop();

        self::assertTrue($a->equals($b));
        self::assertSame($a->hash(), $b->hash());
    }

    public function test_equality_rejects_non_queue(): void
    {
        $q = PersistentQueue::fromArray($this->hasher, $this->equalizer, [1, 2]);

        self::assertFalse($q->equals([1, 2]));
        self::assertFalse($q->equals(null));
    }

    public function test_with_meta_preserves_contents(): void
    {
        $q = PersistentQueue::fromArray($this->hasher, $this->equalizer, [1, 2]);
        $meta = Phel::map(Phel::keyword('tag'), 'demo');

        $tagged = $q->withMeta($meta);

        self::assertSame($meta, $tagged->getMeta());
        self::assertTrue($q->equals($tagged));
    }

    /**
     * Regression for the int->float overflow in the `31 * $hash + ...`
     * accumulator: a long queue must still return a stable int hash instead
     * of throwing a TypeError on the `?int $hashCache` assignment.
     */
    public function test_hash_large_queue_returns_stable_int(): void
    {
        $q = PersistentQueue::fromArray($this->hasher, $this->equalizer, range(0, 999));

        $hash = $q->hash();

        self::assertIsInt($hash);
        self::assertSame($hash, $q->hash());
    }
}
