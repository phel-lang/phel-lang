<?php

declare(strict_types=1);

namespace Phel\Printer\ElementPrinter;

final class NullPrinter implements ElementPrinterInterface
{
    /**
     * @param mixed $form
     */
    public function print($form): string
    {
        return 'nil';
    }
}
