<?php

declare(strict_types=1);

namespace Phel\Shared\Printer\TypePrinter;

use Phel\Lang\PhelVar;

/**
 * @implements TypePrinterInterface<PhelVar>
 */
final class VarPrinter implements TypePrinterInterface
{
    use ColorizeTrait;
    use WithColorTrait;

    private const string COLOR = '0;91';

    /**
     * @param PhelVar $form
     */
    public function print(mixed $form): string
    {
        return $this->colorize("#'" . $form->getFullName(), self::COLOR);
    }
}
