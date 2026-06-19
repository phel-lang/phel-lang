<?php

declare(strict_types=1);

namespace Phel\Shared\Printer\TypePrinter;

use function is_float;
use function is_infinite;
use function is_nan;
use function preg_match;
use function sprintf;

/**
 * @implements TypePrinterInterface<float|int>
 */
final class NumberPrinter implements TypePrinterInterface
{
    use WithColorTrait;

    /**
     * @param float|int $form
     */
    public function print(mixed $form): string
    {
        if (is_float($form)) {
            return $this->color($this->printFloat($form));
        }

        return $this->color((string) $form);
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

        $str = (string) $form;

        // A plain cast drops the trailing `.0` (so `17.0` becomes `17`),
        // which would make a float indistinguishable from an int. Restore
        // it unless the cast already carries a decimal point or exponent.
        return preg_match('/[.eE]/', $str) === 1 ? $str : $str . '.0';
    }

    private function color(string $str): string
    {
        if ($this->withColor) {
            return sprintf("\033[0;92m%s\033[0m", $str);
        }

        return $str;
    }
}
