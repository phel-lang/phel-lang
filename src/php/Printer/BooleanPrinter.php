<?php

declare(strict_types=1);

namespace Phel\Printer;

final class BooleanPrinter implements PrinterInterface
{
    /**
     * @param bool $form
     */
    public function print($form): string
    {
        return $form === true
            ? 'true'
            : 'false';
    }
}
