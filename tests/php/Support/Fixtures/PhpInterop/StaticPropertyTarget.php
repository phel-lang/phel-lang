<?php

declare(strict_types=1);

namespace PhelTest\Support\Fixtures\PhpInterop;

/**
 * Carries a static property and a class constant under the same name, which
 * PHP allows because it files the two separately. Only the `$` sigil tells
 * them apart, which is what the static-property interop tests pin.
 */
final class StaticPropertyTarget
{
    public const string slot = 'i-am-a-constant';

    public static mixed $slot = null;

    public static function reset(): void
    {
        self::$slot = null;
    }
}
