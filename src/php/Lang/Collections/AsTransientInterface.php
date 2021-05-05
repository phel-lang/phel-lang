<?php

declare(strict_types=1);

namespace Phel\Lang\Collections;

/**
 * @template TransientType
 */
interface AsTransientInterface
{
    /**
     * @return TransientType
     */
    public function asTransient();
}
