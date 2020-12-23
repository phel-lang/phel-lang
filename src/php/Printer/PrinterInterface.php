<?php

declare(strict_types=1);

namespace Phel\Printer;

interface PrinterInterface
{
    /**
     * @param mixed $form
     */
    public function print($form): string;
}
