<?php

declare(strict_types=1);

namespace Phel\Printer;

interface PrinterInterface
{
    /**
     * Converts a form to a printable string.
     */
    public function print(mixed $form): string;
}
