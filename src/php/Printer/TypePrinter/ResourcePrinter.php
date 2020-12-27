<?php

declare(strict_types=1);

namespace Phel\Printer\TypePrinter;

/**
 * @implements TypePrinterInterface<resource>
 */
final class ResourcePrinter implements TypePrinterInterface
{
    /**
     * @param resource $form
     */
    public function print($form): string
    {
        return '<PHP Resource id #' . (string)$form . '>';
    }
}
