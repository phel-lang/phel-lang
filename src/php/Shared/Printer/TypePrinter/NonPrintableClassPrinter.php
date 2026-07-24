<?php

declare(strict_types=1);

namespace Phel\Shared\Printer\TypePrinter;

/**
 * @implements TypePrinterInterface<object>
 */
final class NonPrintableClassPrinter implements TypePrinterInterface
{
    use ColorizeTrait;
    use WithColorTrait;

    private const string COLOR = '1;35';

    /**
     * @param object $form
     */
    public function print(mixed $form): string
    {
        return 'Printer cannot print this type: ' . $this->colorize($form::class, self::COLOR);
    }
}
