<?php

declare(strict_types=1);

namespace Phel\Lang;

use Iterator;
use MultipleIterator;

/**
 * Try to compare directly
 * This is faster if it works but it will not catch all cases
 * For example `(= @[:a] @[:a])` will fail because the Keyword Objects are
 * not the same reference but.
 * We could change the comparison operator to `==`. This will make the above
 * example work but fail another example `(= @[1] @["1"])
 * It would be helpful if the Object-comparison RFC (https://wiki.php.net/rfc/object-comparison)
 * would have been accepted but it is not.
 */
trait IteratorComparatorTrait
{
    private function hasSameKeyValues(Iterator $other): bool
    {
        if (!($this instanceof Iterator)) {
            return false;
        }

        if (iterator_to_array($other) === iterator_to_array($this)) {
            return true;
        }

        // If direct comparison is not working
        // we have to iterate over all elements and compare the keys and values.
        $mi = new MultipleIterator();
        $mi->attachIterator($this);
        $mi->attachIterator($other);

        foreach ($mi as $keys => $values) {
            [$k1, $k2] = $keys;
            [$v1, $v2] = $values;

            if ($k1 !== $k2 || !$this->areEquals($v1, $v2)) {
                return false;
            }
        }

        return true;
    }

    abstract protected function areEquals($a, $b): bool;
}
