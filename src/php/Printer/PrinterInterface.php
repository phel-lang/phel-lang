<?php

declare(strict_types=1);

namespace Phel\Printer;

/**
 * @template T
 */
interface PrinterInterface
{
    /**
     * @param T $form
     */
    public function print($form): string;
}
