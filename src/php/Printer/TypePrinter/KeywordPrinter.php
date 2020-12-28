<?php

declare(strict_types=1);

namespace Phel\Printer\TypePrinter;

use Phel\Lang\Keyword;

/**
 * @implements TypePrinterInterface<Keyword>
 */
final class KeywordPrinter implements TypePrinterInterface
{
    /**
     * @param Keyword $form
     */
    public function print($form): string
    {
        return ':' . $form->getName();
    }
}
