<?php

declare(strict_types=1);

namespace Phel\Printer\TypePrinter;

/**
 * @implements TypePrinterInterface<object>
 */
final class AnonymousClassPrinter implements TypePrinterInterface
{
    use WithColorTrait;

    /**
     * @param object $form
     */
    public function print($form): string
    {
        return '<PHP-AnonymousClass>';
    }
}
