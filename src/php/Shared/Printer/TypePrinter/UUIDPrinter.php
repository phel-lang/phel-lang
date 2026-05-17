<?php

declare(strict_types=1);

namespace Phel\Shared\Printer\TypePrinter;

use Phel\Lang\UUID;

use function sprintf;

/**
 * @implements TypePrinterInterface<UUID>
 */
final class UUIDPrinter implements TypePrinterInterface
{
    use WithColorTrait;

    /**
     * @param UUID $form
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
