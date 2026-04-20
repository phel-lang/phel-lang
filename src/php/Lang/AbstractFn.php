<?php

declare(strict_types=1);

namespace Phel\Lang;

use ReflectionClass;
use Stringable;

use function is_string;

abstract class AbstractFn implements FnInterface, MetaInterface, Stringable
{
    use MetaTrait;

    public function __toString(): string
    {
        $boundTo = new ReflectionClass($this)->getConstant('BOUND_TO');

        if (!is_string($boundTo) || $boundTo === '') {
            return '<function>';
        }

        $lastSeparator = strrpos($boundTo, '\\');
        $encodedName = $lastSeparator !== false
            ? substr($boundTo, $lastSeparator + 1)
            : $boundTo;

        return '<function:' . str_replace('_', '-', $encodedName) . '>';
    }
}
