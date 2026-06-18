<?php

declare(strict_types=1);

namespace Phel\Shared\Printer\TypePrinter;

use function is_float;
use function is_nan;
use function sprintf;

/**
 * @implements TypePrinterInterface<float|int>
 */
final class NumberPrinter implements TypePrinterInterface
{
    use WithColorTrait;

    /**
     * @param float|int $form
     */
    public function print(mixed $form): string
    {
        // Casting NAN to string emits a PHP warning; render it explicitly,
        // matching the `INF`/`-INF` rendering PHP produces for infinities.
        if (is_float($form) && is_nan($form)) {
            return $this->color('NAN');
        }

        return $this->color((string) $form);
    }

    private function color(string $str): string
    {
        if ($this->withColor) {
            return sprintf("\033[0;92m%s\033[0m", $str);
        }

        return $str;
    }
}
