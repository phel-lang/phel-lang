<?php

declare(strict_types=1);

namespace Phel\Interop\Domain\ReadModel;

use Phel\Lang\FnInterface;

final readonly class FunctionToExport
{
    public function __construct(private FnInterface $fn)
    {
    }

    public function fn(): FnInterface
    {
        return $this->fn;
    }
}
