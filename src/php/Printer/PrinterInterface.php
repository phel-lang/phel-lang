<?php

declare(strict_types=1);

namespace Phel\Printer;

interface PrinterInterface
{
    /**
     * @psalm-suppress MissingParamType
     */
    public function print($form): string;
}
