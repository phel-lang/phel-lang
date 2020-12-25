<?php

declare(strict_types=1);

namespace Phel\Printer;

use Phel\Lang\Keyword;

/**
 * @implements PrinterInterface<Keyword>
 */
final class KeywordPrinter implements PrinterInterface
{
    /**
     * @param Keyword $form
     */
    public function print($form): string
    {
        return ':' . $form->getName();
    }
}
