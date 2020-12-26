<?php

declare(strict_types=1);

namespace Phel\Printer\ElementPrinter;

/**
 * @implements ElementPrinterInterface<resource>
 */
final class ResourcePrinter implements ElementPrinterInterface
{
    /**
     * @param resource $form
     */
    public function print($form): string
    {
        return '<PHP Resource id #' . (string)$form . '>';
    }
}
