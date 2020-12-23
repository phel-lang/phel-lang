<?php

declare(strict_types=1);

namespace Phel\Printer;

final class ResourcePrinter implements PrinterInterface
{
    /**
     * @psalm-suppress MoreSpecificImplementedParamType
     *
     * @param resource $form
     */
    public function print($form): string
    {
        return '<PHP Resource id #' . (string)$form . '>';
    }
}