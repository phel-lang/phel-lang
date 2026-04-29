<?php

declare(strict_types=1);

namespace Phel\Printer\TypePrinter;

use Phel\Lang\Keyword;

use function sprintf;

/**
 * @implements TypePrinterInterface<Keyword>
 */
final class KeywordPrinter implements TypePrinterInterface
{
    use WithColorTrait;

    /**
     * @param Keyword $form
     */
    public function print(mixed $form): string
    {
        return $this->color($form->__toString());
    }

    private function color(string $str): string
    {
        if ($this->withColor) {
            return sprintf("\033[0;93m%s\033[0m", $str);
        }

        return $str;
    }
}
