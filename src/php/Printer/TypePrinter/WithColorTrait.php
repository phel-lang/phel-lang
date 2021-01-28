<?php

declare(strict_types=1);

namespace Phel\Printer\TypePrinter;

trait WithColorTrait
{
    private bool $withColor;

    public function __construct(bool $withColor = false)
    {
        $this->withColor = $withColor;
    }
}
