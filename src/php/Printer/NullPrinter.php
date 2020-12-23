<?php

declare(strict_types=1);

namespace Phel\Printer;

final class NullPrinter implements PrinterInterface
{
    /**
     * @param mixed $form
     */
    public function print($form): string
    {
        return 'nil';
    }
}
