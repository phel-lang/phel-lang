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

    private const string COLOR = '0;92';

    public function __construct(
        private bool $withColor = false,
        private bool $readable = false,
    ) {}

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

    private function printFloat(float $form): string
    {
        if (is_nan($form)) {
            return $this->readable ? '##NaN' : 'NaN';
        }

        if (is_infinite($form)) {
            return $this->readable
                ? ($form > 0 ? '##Inf' : '##-Inf')
                : ($form > 0 ? 'Infinity' : '-Infinity');
        }

        return FloatFormatter::format($form);
    }
}
