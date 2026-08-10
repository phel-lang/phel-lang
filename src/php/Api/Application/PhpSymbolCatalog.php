<?php

declare(strict_types=1);

namespace Phel\Api\Application;

use function get_declared_classes;
use function get_defined_functions;

/**
 * Catalog of the native PHP symbols reachable through the `php/` interop
 * prefix: functions, classes, and superglobal variables.
 *
 * Functions and classes are discovered at runtime and lazily cached, for two
 * reasons:
 * - Avoid scanning `get_defined_functions()` / `get_declared_classes()`
 *   on every REPL completion request.
 * - Replace the previous static-field caching inside `ReplCompleter`
 *   so each REPL completer can be constructed in isolation and tested
 *   without leaking state across cases.
 *
 * Superglobals have nothing to discover: the set is fixed by the language, so
 * it is spelled out as a constant and shared by every completion surface.
 *
 * @internal
 */
final class PhpSymbolCatalog
{
    /**
     * The nine PHP superglobals, each mapped to the one-line summary shown as
     * completion documentation and hover text. All of them are arrays.
     *
     * @see https://www.php.net/manual/en/language.variables.superglobals.php
     */
    public const array SUPERGLOBALS = [
        '$GLOBALS' => 'References all variables available in global scope.',
        '$_SERVER' => 'Server and execution environment information.',
        '$_GET' => 'HTTP GET variables.',
        '$_POST' => 'HTTP POST variables.',
        '$_FILES' => 'HTTP file upload variables.',
        '$_COOKIE' => 'HTTP cookies.',
        '$_SESSION' => 'Session variables.',
        '$_REQUEST' => 'HTTP request variables.',
        '$_ENV' => 'Environment variables.',
    ];

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
