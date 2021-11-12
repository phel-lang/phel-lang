<?php

declare(strict_types=1);

namespace Phel\Printer\TypePrinter;

use Phel\Lang\Keyword;

/**
 * @implements TypePrinterInterface<Keyword>
 */
final class KeywordPrinter implements TypePrinterInterface
{
    use WithColorTrait;

    /**
     * @param Keyword $form
     */
    public function print($form): string
    {
        if ($form->getNamespace()) {
            return $this->color(':' . $form->getNamespace() . '/' . $form->getName());
        }

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
