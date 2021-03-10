<?php

declare(strict_types=1);

namespace PhelTest\Benchmark\Lang\Collections\Vector;

use Phel\Lang\Collections\Vector\PersistentVector;
use Phel\Lang\Collections\Vector\TransientVector;

class VectorAddBench
{
    const MAX_ADD = 1000;

    /**
     * @Revs(1000)
     */
    public function benchMutablePhpArrayAdd(): void
    {
        $arr = [];
        for ($i = 0; $i < self::MAX_ADD; $i++) {
            $arr[] = $i;
        }
    }

    /**
     * @Revs(1000)
     */
    public function benchPersistentVectorAdd(): void
    {
        $vector = PersistentVector::empty();
        for ($i = 0; $i < self::MAX_ADD; $i++) {
            $vector = $vector->append($i);
        }
    }

    /**
     * @Revs(1000)
     */
    public function benchTransientVectorAdd(): void
    {
        $vector = TransientVector::empty();
        for ($i = 0; $i < self::MAX_ADD; $i++) {
            $vector = $vector->append($i);
        }
    }

    /**
     * @Revs(1000)
     */
    public function benchVectorAAdd(): void
    {
        $vector = new VectorA(0, [], []);
        for ($i = 0; $i < self::MAX_ADD; $i++) {
            $vector = $vector->append($i);
        }
    }

    /**
     * @Revs(1000)
     */
    public function benchPhpArrayCopyAndAdd(): void
    {
        $arr = [];
        for ($i = 0; $i < self::MAX_ADD; $i++) {
            $newArr = $arr;
            $newArr[] = $i;
            $arr = $newArr;
        }
    }

    /**
     * @Revs(1000)
     */
    public function benchSimpleImmutable(): void
    {
        $siv = new SimpleImmutableVector([]);
        for ($i = 0; $i < self::MAX_ADD; $i++) {
            $siv = $siv->append($i);
        }
    }
}
