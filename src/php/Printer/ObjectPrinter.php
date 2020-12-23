<?php

declare(strict_types=1);

namespace Phel\Printer;

final class ObjectPrinter implements PrinterInterface
{
    /**
     * @psalm-suppress MoreSpecificImplementedParamType
     *
     * @param object $form
     */
    public function print($form): string
    {
        return '<PHP-Object(' . get_class($form) . ')>';
    }
}
