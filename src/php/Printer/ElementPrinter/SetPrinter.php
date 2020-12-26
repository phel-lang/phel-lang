<?php

declare(strict_types=1);

namespace Phel\Printer\ElementPrinter;

use Phel\Lang\Set;
use Phel\Printer;

/**
 * @implements ElementPrinterInterface<Set>
 */
final class SetPrinter implements ElementPrinterInterface
{
    private Printer $printer;

    public function __construct(Printer $printer)
    {
        $this->printer = $printer;
    }

    /**
     * @param Set $form
     */
    public function print($form): string
    {
        $values = array_map(
            fn ($elem): string => $this->printer->print($elem),
            $form->toPhpArray()
        );

        return '(set' . (count($values) > 0 ? ' ' : '') . implode(' ', $values) . ')';
    }
}
