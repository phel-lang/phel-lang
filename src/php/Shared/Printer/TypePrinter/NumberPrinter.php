<?php

declare(strict_types=1);

namespace Phel\Shared\Printer\TypePrinter;

use Phel\Shared\FloatFormatter;

use function is_float;
use function is_infinite;
use function is_nan;

/**
 * @implements TypePrinterInterface<float|int>
 */
final class NumberPrinter implements TypePrinterInterface
{
    use ColorizeTrait;
    use WithColorTrait;

    private const string COLOR = '0;92';

    /**
     * @param float|int $form
     */
    public function print(mixed $form): string
    {
        if (is_float($form)) {
            return $this->colorize($this->printFloat($form), self::COLOR);
        }

        return $this->colorize((string) $form, self::COLOR);
    }

    /**
     * Renders a float so it agrees with `str`/`float-to-str` (in
     * `src/phel/core/strings.phel`): integer-valued floats keep their `.0`,
     * and the special values spell out readably. Keep both in lockstep.
     */
    private function printFloat(float $form): string
    {
        if (is_nan($form)) {
            return 'NaN';
        }

        if (is_infinite($form)) {
            return $form > 0 ? 'Infinity' : '-Infinity';
        }

        return FloatFormatter::format($form);
    }
}
