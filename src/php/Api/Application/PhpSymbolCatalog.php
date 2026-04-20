<?php

declare(strict_types=1);

namespace Phel\Api\Application;

use function get_declared_classes;
use function get_defined_functions;

/**
 * Lazy cache over native PHP function and class lists.
 *
 * Two goals:
 * - Avoid scanning `get_defined_functions()` / `get_declared_classes()`
 *   on every REPL completion request.
 * - Replace the previous static-field caching inside `ReplCompleter`
 *   so each REPL completer can be constructed in isolation and tested
 *   without leaking state across cases.
 */
final class PhpSymbolCatalog
{
    /** @var list<callable-string>|null */
    private ?array $functions = null;

    /** @var list<class-string>|null */
    private ?array $classes = null;

    /**
     * @return list<callable-string>
     */
    public function functions(): array
    {
        if ($this->functions === null) {
            $this->functions = get_defined_functions()['internal'];
        }

        return $this->functions;
    }

    /**
     * @return list<class-string>
     */
    public function classes(): array
    {
        if ($this->classes === null) {
            $this->classes = get_declared_classes();
        }

        return $this->classes;
    }
}
