<?php

declare(strict_types=1);

namespace Phel\Printer\TypePrinter;

use function sprintf;

/**
 * @implements TypePrinterInterface<bool>
 */
final class BooleanPrinter implements TypePrinterInterface
{
    use WithColorTrait;

    /**
     * @param bool $form
     */
    public function print(mixed $form): string
    {
        $str = $form ? 'true' : 'false';

        return $this->color($str);
    }

    private function color(string $str): string
    {
        if ($this->withColor) {
            return sprintf("\033[0;94m%s\033[0m", $str);
        }

        return $str;
    }
}
