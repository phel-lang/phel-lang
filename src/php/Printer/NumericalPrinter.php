<?php

declare(strict_types=1);

namespace Phel\Printer;

final class NumericalPrinter
{
    /**
     * @param int|float $form
     */
    public function print($form): string
    {
        return (string)$form;
    }
}
