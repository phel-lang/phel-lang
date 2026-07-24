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
    use ColorizeTrait;
    use WithColorTrait;

    private const string COLOR = '0;92';

    /**
     * @param UUID $form
     */
    public function print(mixed $form): string
    {
        return $this->colorize(sprintf('#uuid "%s"', $form), self::COLOR);
    }
}
