<?php

declare(strict_types=1);

namespace Phel\Shared\Printer\TypePrinter;

use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Shared\Printer\PrinterInterface;

use function sprintf;

/**
 * @implements TypePrinterInterface<PersistentMapInterface<mixed, mixed>>
 */
final readonly class PersistentMapPrinter implements TypePrinterInterface
{
    public function __construct(private PrinterInterface $printer) {}

    /**
     * @param PersistentMapInterface<mixed, mixed> $form
     */
    public function print(mixed $form): string
    {
        $prefix = '{';
        $suffix = '}';

        $pairs = [];
        foreach ($form as $key => $value) {
            $pairs[] = sprintf(
                '%s %s',
                $this->printer->print($key),
                $this->printer->print($value),
            );
        }

        return $prefix . implode(', ', $pairs) . $suffix;
    }
}
