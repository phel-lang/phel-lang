<?php

declare(strict_types=1);

namespace Phel\Shared\Printer\TypePrinter;

/**
 * @implements TypePrinterInterface<bool>
 */
final class BooleanPrinter implements TypePrinterInterface
{
    use ColorizeTrait;
    use WithColorTrait;

    private const string COLOR = '0;94';

    /**
     * @param bool $form
     */
    public function print(mixed $form): string
    {
        $str = $form ? 'true' : 'false';

        return $this->colorize($str, self::COLOR);
    }
}
