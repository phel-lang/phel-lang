<?php

declare(strict_types=1);

namespace Phel\Shared\Printer\TypePrinter;

/**
 * @template-implements TypePrinterInterface<null>
 */
final class NullPrinter implements TypePrinterInterface
{
    use ColorizeTrait;
    use WithColorTrait;

    private const string COLOR = '0;96';

    public function print(mixed $form): string
    {
        return $this->colorize('nil', self::COLOR);
    }
}
