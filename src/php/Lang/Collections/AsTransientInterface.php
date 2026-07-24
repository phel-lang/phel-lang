<?php

declare(strict_types=1);

namespace Phel\Lang\Collections;

/**
 * @template TTransient
 */
interface AsTransientInterface
{
    /**
     * @return TTransient
     */
    public function asTransient();
}
