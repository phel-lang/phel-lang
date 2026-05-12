<?php

declare(strict_types=1);

namespace Phel\Shared\Printer\TypePrinter;

/**
 * @template T
 */
interface TypePrinterInterface
{
    /**
     * @param T $form
     */
    public function print(mixed $form): string;
}
