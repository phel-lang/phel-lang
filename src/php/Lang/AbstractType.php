<?php

declare(strict_types=1);

namespace Phel\Lang;

abstract class AbstractType implements MetaInterface, SourceLocationInterface, EqualsInterface, HashableInterface
{
    use SourceLocationTrait;
    use MetaTrait;

    /**
     * @param mixed|null $a
     * @param mixed|null $b
     */
    protected function areEquals($a, $b): bool
    {
        if ($a instanceof AbstractType) {
            return $a->equals($b);
        }

        return $a === $b;
    }
}
