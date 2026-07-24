<?php

declare(strict_types=1);

namespace Phel\Shared\Printer\TypePrinter;

use Phel\Lang\Ratio;

/**
 * @implements TypePrinterInterface<Ratio>
 */
final class RatioPrinter implements TypePrinterInterface
{
    use ColorizeTrait;
    use WithColorTrait;

    private const string COLOR = '0;92';

    /**
     * @param Ratio $form
     */
    public function print(mixed $form): string
    {
        return $this->colorize($form->__toString(), self::COLOR);
    }
}
