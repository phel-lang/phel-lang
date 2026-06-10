<?php

declare(strict_types=1);

namespace Phel\Shared\Printer\TypePrinter;

use Stringable;

/**
 * Selected by the dispatcher only for objects exposing `__toString()`; since
 * PHP 8 such classes implicitly implement `Stringable`.
 *
 * @implements TypePrinterInterface<Stringable>
 */
final class ToStringPrinter implements TypePrinterInterface
{
    /**
     * @param Stringable $form
     */
    public function print(mixed $form): string
    {
        return (string) $form;
    }
}
