<?php

declare(strict_types=1);

namespace Phel\Printer;

use Phel\Lang\Keyword;

final class KeywordPrinter implements PrinterInterface
{
    /**
     * @psalm-suppress MoreSpecificImplementedParamType
     *
     * @param Keyword $form
     */
    public function print($form): string
    {
        return ':' . $form->getName();
    }
}
