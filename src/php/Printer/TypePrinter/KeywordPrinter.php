<?php

declare(strict_types=1);

namespace Phel\Printer\TypePrinter;

use Phel\Lang\Keyword;

/**
 * @implements TypePrinterInterface<Keyword>
 */
final class KeywordPrinter implements TypePrinterInterface
{
    private bool $withColor;

    public function __construct(bool $withColor = false)
    {
        $this->withColor = $withColor;
    }

    /**
     * @param Keyword $form
     */
    public function print($form): string
    {
        return $this->color(':' . $form->getName());
    }

    private function color(string $str): string
    {
        if ($this->withColor) {
            return sprintf("\033[0;93m%s\033[0m", $str);
        }

        return $str;
    }
}
