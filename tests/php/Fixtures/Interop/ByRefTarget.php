<?php

declare(strict_types=1);

namespace PhelTest\Fixtures\Interop;

/**
 * Exposes a by-reference parameter so the Phel-level `php/ref` interop tests
 * can observe a write made through an output parameter.
 */
final class ByRefTarget
{
    public function writeInto(mixed &$out, mixed $value): void
    {
        $out = $value;
    }
}
