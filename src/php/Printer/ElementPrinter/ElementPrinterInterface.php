<?php

declare(strict_types=1);

namespace Phel\Printer\ElementPrinter;

/**
 * @template T
 */
interface ElementPrinterInterface
{
    /**
     * @param T $form
     */
    public function print($form): string;
}
