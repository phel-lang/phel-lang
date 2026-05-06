<?php

declare(strict_types=1);

namespace Phel\Printer\TypePrinter;

use Phel\Lang\Rational;

use function sprintf;

/**
 * @implements TypePrinterInterface<Rational>
 */
final class RationalPrinter implements TypePrinterInterface
{
    use WithColorTrait;

    /**
     * @param Rational $form
     */
    public function print(mixed $form): string
    {
        return $this->color($form->__toString());
    }

    private function color(string $str): string
    {
        if ($this->withColor) {
            return sprintf("\033[0;92m%s\033[0m", $str);
        }

        return $str;
    }
}
