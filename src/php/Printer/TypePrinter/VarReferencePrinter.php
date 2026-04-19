<?php

declare(strict_types=1);

namespace Phel\Printer\TypePrinter;

use Phel\Lang\VarReference;

use function sprintf;

/**
 * @implements TypePrinterInterface<VarReference>
 */
final class VarReferencePrinter implements TypePrinterInterface
{
    use WithColorTrait;

    /**
     * @param VarReference $form
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
