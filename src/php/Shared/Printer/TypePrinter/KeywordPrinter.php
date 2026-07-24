<?php

declare(strict_types=1);

namespace Phel\Shared\Printer\TypePrinter;

use Phel\Lang\Keyword;

/**
 * @implements TypePrinterInterface<Keyword>
 */
final class KeywordPrinter implements TypePrinterInterface
{
    use ColorizeTrait;
    use WithColorTrait;

    private const string COLOR = '0;93';

    /**
     * @param Keyword $form
     */
    public function print(mixed $form): string
    {
        return $this->colorize($form->__toString(), self::COLOR);
    }
}
