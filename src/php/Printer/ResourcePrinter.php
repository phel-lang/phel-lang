<?php

declare(strict_types=1);

namespace Phel\Printer;

/**
 * @implements PrinterInterface<resource>
 */
final class ResourcePrinter implements PrinterInterface
{
    /**
     * @param resource $form
     */
    public function print($form): string
    {
        return '<PHP Resource id #' . (string)$form . '>';
    }
}
