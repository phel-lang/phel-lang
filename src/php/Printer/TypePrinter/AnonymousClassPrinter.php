<?php

declare(strict_types=1);

namespace Phel\Printer\TypePrinter;

/**
 * @implements TypePrinterInterface<callable>
 */
final class AnonymousClassPrinter implements TypePrinterInterface
{
    /**
     * @param callable $form
     */
    public function print($form): string
    {
        return '<PHP-AnonymousClass>';
    }
}
