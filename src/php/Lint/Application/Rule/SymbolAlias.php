<?php

declare(strict_types=1);

namespace Phel\Lint\Application\Rule;

use function count;
use function preg_split;

/**
 * Resolves the implicit alias a `(:use ...)` / `(:require ...)` entry binds
 * when no explicit `:as` is given: the last segment of the symbol.
 *
 * Phel accepts both separators, and the analyzer treats them alike
 * (`NsSymbol::createAliasFromSymbol` splits on `.`, and the reader accepts
 * `\`), so a rule that splits on only one of them computes an alias nobody
 * ever writes and then reports the entry as unused.
 */
final class SymbolAlias
{
    public static function lastSegment(string $name): string
    {
        $parts = preg_split('/[.\\\\]/', $name);
        if ($parts === false) {
            return $name;
        }

        return $parts[count($parts) - 1];
    }
}
