<?php

declare(strict_types=1);

namespace Phel\Shared\Printer\TypePrinter;

use Phel\Lang\PhelVar;

use function sprintf;

/**
 * @implements TypePrinterInterface<PhelVar>
 */
final class VarPrinter implements TypePrinterInterface
{
    use WithColorTrait;

    /**
     * @param PhelVar $form
     */
    public function print(mixed $form): string
    {
        return $this->color("#'" . $form->getFullName());
    }

    private function color(string $str): string
    {
        if ($this->withColor) {
            return sprintf("\033[0;91m%s\033[0m", $str);
        }

        return $str;
    }
}
