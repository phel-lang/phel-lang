<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang\Collections\Vector;

use Phel;
use Phel\Lang\Collections\Vector\RangeIterator;
use PHPUnit\Framework\TestCase;

final class RangeIteratorTest extends TestCase
{
    public function test_range_iterator_with32_elements(): void
    {
        $it = new RangeIterator(
            Phel::persistentVectorFromArray(range(0, 31)),
            0,
            32,
        );

        self::assertSame(range(0, 31), iterator_to_array($it));
    }

    public function test_iterator_can_be_reused_after_rewind(): void
    {
        $start = 60;
        $end = 90;

        $it = new RangeIterator(
            Phel::persistentVectorFromArray(range(0, 100)),
            $start,
            $end,
        );

        $expected = iterator_to_array($it);

        // Reusing the iterator should yield the same result
        self::assertSame($expected, iterator_to_array($it));
    }
}
