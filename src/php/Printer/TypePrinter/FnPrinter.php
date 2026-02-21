<?php

declare(strict_types=1);

namespace Phel\Printer\TypePrinter;

use ReflectionClass;

/**
 * @implements TypePrinterInterface<object>
 */
final class FnPrinter implements TypePrinterInterface
{
    /**
     * @param object $form
     */
    public function print(mixed $form): string
    {
        $name = $this->extractName($form);

        if ($name !== null) {
            return '<function:' . $name . '>';
        }

        return '<function>';
    }

    private function extractName(object $form): ?string
    {
        $boundTo = (new ReflectionClass($form))->getConstant('BOUND_TO');

        if ($boundTo === false || $boundTo === '') {
            return null;
        }

        $lastSeparator = strrpos((string) $boundTo, '\\');
        $encodedName = $lastSeparator !== false
            ? substr((string) $boundTo, $lastSeparator + 1)
            : $boundTo;

        /** @var string */
        return str_replace('_', '-', $encodedName);
    }
}
