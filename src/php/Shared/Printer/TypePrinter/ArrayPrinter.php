<?php

declare(strict_types=1);

namespace Phel\Shared\Printer\TypePrinter;

use Phel\Shared\Printer\PrinterInterface;

use function count;
use function sprintf;

/**
 * @implements TypePrinterInterface<array<int|string, mixed>>
 */
final readonly class ArrayPrinter implements TypePrinterInterface
{
    use ColorizeTrait;

    private const string COLOR = '0;37';

    public function __construct(
        private PrinterInterface $printer,
        private bool $withColor = false,
    ) {}

    /**
     * @param array<int|string, mixed> $form
     */
    public function print(mixed $form): string
    {
        $arr = $this->isList($form)
            ? $this->formatValuesFromList($form)
            : $this->formatKeyValuesFromDict($form);

        return sprintf('<PHP-Array [%s]>', $this->colorize(implode(', ', $arr), self::COLOR));
    }

    /**
     * @param array<int|string, mixed> $form
     */
    private function isList(array $form): bool
    {
        return array_keys($form) === range(0, count($form) - 1);
    }

    /**
     * @param array<int|string, mixed> $form
     *
     * @return list<string>
     */
    private function formatValuesFromList(array $form): array
    {
        $result = [];
        foreach ($form as $v) {
            $result[] = $this->printer->print($v);
        }

        return $result;
    }

    /**
     * @param array<int|string, mixed> $form
     *
     * @return list<string>
     */
    private function formatKeyValuesFromDict(array $form): array
    {
        $result = [];
        foreach ($form as $k => $v) {
            $result[] = sprintf('%s:%s', $this->printer->print($k), $this->printer->print($v));
        }

        return $result;
    }
}
