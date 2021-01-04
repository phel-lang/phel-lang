<?php

declare(strict_types=1);

namespace Phel\Lang;

interface ConcatInterface
{
    /**
     * Concatenates a value to the data structure.
     *
     * @param mixed[] $xs The value to concatenate
     */
    public function concat($xs): ConcatInterface;
}
