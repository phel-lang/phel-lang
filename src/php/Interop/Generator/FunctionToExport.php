<?php

declare(strict_types=1);

namespace Phel\Interop\Generator;

use Phel\Lang\FnInterface;

final class FunctionToExport
{
    public FnInterface $fn;

    public function __construct(FnInterface $fn)
    {
        $this->fn = $fn;
    }
}
