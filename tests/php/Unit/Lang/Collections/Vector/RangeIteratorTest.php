<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang\Collections\Vector;

use Phel\Lang\Collections\Vector\RangeIterator;
use Phel\Lang\TypeFactory;
use PHPUnit\Framework\TestCase;

final class RangeIteratorTest extends TestCase
{
    public function testRangeIteratorWith32Elements(): void
    {
        $it = new RangeIterator(
            TypeFactory::getInstance()->persistentVectorFromArray(range(0, 31)),
            0,
            32
        );

        $this->assertEquals(range(0, 31), iterator_to_array($it));
    }
}
