<?php

declare(strict_types=1);

namespace Phel\Printer\TypePrinter;

trait WithColorTrait
{
    public function __construct(private bool $withColor = false)
    {
    }
}
