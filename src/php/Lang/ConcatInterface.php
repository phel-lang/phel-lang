<?php

declare(strict_types=1);

namespace Phel\Lang;

use NoDiscard;

/**
 * @template T of ConcatInterface
 */
interface ConcatInterface
{
    /**
     * Concatenates a value to the data structure.
     *
     * @param array<int, mixed> $xs The value to concatenate
     *
     * @return T
     */
    #[NoDiscard('the result is a new instance, the receiver is unchanged')]
    public function concat($xs);
}
