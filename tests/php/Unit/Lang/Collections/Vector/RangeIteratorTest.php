<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang\Collections\Vector;

use Phel\Lang\Collections\Vector\RangeIterator;
use Phel\Lang\TypeFactory;
use PHPUnit\Framework\TestCase;

final class RangeIteratorTest extends TestCase
{
    public function test_range_iterator_with32_elements(): void
    {
        $it = new RangeIterator(
            TypeFactory::getInstance()->persistentVectorFromArray(range(0, 31)),
            0,
            32
        );

        self::assertEquals(range(0, 31), iterator_to_array($it));
    }
}
