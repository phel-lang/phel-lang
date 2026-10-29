<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

use Phel\Lang\Symbol;
use Phel\Shared\Munge;

use function preg_match;
use function str_starts_with;

/**
 * @internal
 */
final class LocalBindingName
{
    public static function isReserved(Symbol $symbol, ?string $phpName = null): bool
    {
        $name = $symbol->getName();
        if ($name === 'this' || ($name[0] ?? '') !== '_' && ($name[0] ?? '') !== '-') {
            return false;
        }

        $name = $phpName ?? new Munge()->encode($name);
        // Plain generated names are unique and never name an emitter scratch slot.
        if ($symbol->isGenerated() && preg_match('/^__phel_[0-9]+(?:_[0-9]+)*$/', $name) === 1) {
            return false;
        }

        return str_starts_with($name, '__truthy')
            || str_starts_with($name, '__or')
            || str_starts_with($name, '__phel');
    }

    public static function shadow(Symbol $symbol): Symbol
    {
        $prefix = self::isReserved($symbol) ? '__phel_local_' : $symbol->getName() . '_';

        return Symbol::gen($prefix)->copyLocationFrom($symbol);
    }
}
