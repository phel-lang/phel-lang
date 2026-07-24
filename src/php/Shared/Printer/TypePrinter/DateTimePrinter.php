<?php

declare(strict_types=1);

namespace Phel\Shared\Printer\TypePrinter;

use DateTimeInterface;

use function sprintf;

/**
 * Prints a `\DateTimeInterface` as the `#inst "<ISO-8601>"` tagged literal
 * that {@see \Phel\Lang\TagHandlers\InstTagHandler} reads, so instants
 * round-trip through `edn`/`pr-str` — analogous to `#uuid`/{@see UUIDPrinter}.
 *
 * Microseconds are emitted only when present, keeping whole-second
 * timestamps compact (`2026-06-17T00:00:00+00:00`).
 *
 * @implements TypePrinterInterface<DateTimeInterface>
 */
final class DateTimePrinter implements TypePrinterInterface
{
    use ColorizeTrait;
    use WithColorTrait;

    private const string COLOR = '0;92';

    /**
     * @param DateTimeInterface $form
     */
    public function print(mixed $form): string
    {
        $format = (int) $form->format('u') === 0
            ? 'Y-m-d\TH:i:sP'
            : 'Y-m-d\TH:i:s.uP';

        return $this->colorize(sprintf('#inst "%s"', $form->format($format)), self::COLOR);
    }
}
