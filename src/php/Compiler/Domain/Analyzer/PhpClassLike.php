<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer;

use function class_exists;
use function interface_exists;
use function trait_exists;

/**
 * Whether PHP knows a name as a class, interface, trait or enum.
 *
 * @internal
 */
final class PhpClassLike
{
    public static function exists(string $name): bool
    {
        // One autoload attempt covers every kind: the loader receives only the
        // name, never which kind the caller asked about, so the non-autoloading
        // probes below already see whatever it defined. Enums need no probe of
        // their own; `class_exists()` reports them too.
        if (class_exists($name)) {
            return true;
        }

        return interface_exists($name, false)
            || trait_exists($name, false);
    }
}
