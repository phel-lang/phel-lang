<?php

declare(strict_types=1);

namespace Phel\Printer;

interface PrinterInterface
{
    /**
     * Converts a form to a printable string.
     *
     * @param mixed $form The form to print
     */
    public function print($form): string;
}
