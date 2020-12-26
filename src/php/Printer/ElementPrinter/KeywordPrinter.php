<?php

declare(strict_types=1);

namespace Phel\Printer\ElementPrinter;

use Phel\Lang\Keyword;

/**
 * @implements ElementPrinterInterface<Keyword>
 */
final class KeywordPrinter implements ElementPrinterInterface
{
    /**
     * @param Keyword $form
     */
    public function print($form): string
    {
        return ':' . $form->getName();
    }
}
