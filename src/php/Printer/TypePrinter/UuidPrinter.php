<?php

declare(strict_types=1);

namespace Phel\Printer\TypePrinter;

use Phel\Lang\Uuid;

use function sprintf;

/**
 * @implements TypePrinterInterface<Uuid>
 */
final class UuidPrinter implements TypePrinterInterface
{
    use WithColorTrait;

    /**
     * @param Uuid $form
     */
    public function print(mixed $form): string
    {
        return $this->color(sprintf('#uuid "%s"', $form));
    }

    private function color(string $str): string
    {
        if ($this->withColor) {
            return sprintf("\033[0;92m%s\033[0m", $str);
        }

        return $str;
    }
}
