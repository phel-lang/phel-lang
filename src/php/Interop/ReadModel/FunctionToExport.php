<?php

declare(strict_types=1);

namespace Phel\Interop\ReadModel;

use Phel\Lang\FnInterface;

final class FunctionToExport
{
    private FnInterface $fn;

    public function __construct(FnInterface $fn)
    {
        $this->fn = $fn;
    }

    public function fn(): FnInterface
    {
        return $this->fn;
    }
}
