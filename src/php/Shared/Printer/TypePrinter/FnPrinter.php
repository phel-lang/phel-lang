<?php

declare(strict_types=1);

namespace Phel\Shared\Printer\TypePrinter;

use ReflectionClass;

use function is_string;
use function str_replace;
use function strrpos;
use function substr;

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
        $boundTo = new ReflectionClass($form)->getConstant('BOUND_TO');

        if (!is_string($boundTo) || $boundTo === '') {
            return null;
        }

        $lastSeparator = strrpos($boundTo, '\\');
        $encodedName = $lastSeparator !== false
            ? substr($boundTo, $lastSeparator + 1)
            : $boundTo;

        return str_replace('_', '-', $encodedName);
    }
}
