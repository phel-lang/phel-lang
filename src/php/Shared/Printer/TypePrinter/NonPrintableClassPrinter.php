<?php

declare(strict_types=1);

namespace Phel\Shared\Printer\TypePrinter;

use function sprintf;

/**
 * Fallback for a host object with no printer of its own. The marker stays a
 * single token because callers splice it into argument lists, such as the
 * frames of a stack trace.
 *
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
        return $this->colorize(sprintf('#<%s>', $form::class), self::COLOR);
    }
}
