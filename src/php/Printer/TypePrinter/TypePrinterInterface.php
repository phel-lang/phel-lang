<?php

declare(strict_types=1);

namespace Phel\Printer\TypePrinter;

/**
 * @template T
 */
interface TypePrinterInterface
{
    /**
     * @param T $form
     */
    public function print($form): string;
}
