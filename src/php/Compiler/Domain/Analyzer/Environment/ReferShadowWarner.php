<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\Environment;

use Phel\Compiler\Domain\Diagnostic\CompilerWarnings;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;

use function sprintf;

/**
 * Detects a `def` whose name is already `:refer`red into the same namespace.
 *
 * The definition wins from there on ({@see SymbolResolver::resolveWithoutAlias()}),
 * so the refer stops being reachable under its bare name. Clojure announces
 * exactly that, once at `def` time rather than at every later call site.
 *
 * The location anchor is the *name* symbol, never the enclosing `def` list:
 * `defn` splices the name through verbatim so it keeps the reader's position,
 * while the list the macro built carries an expansion origin in
 * `src/phel/core/defs.phel`. Anchoring on the list would put every `defn`
 * warning inside the bundled stdlib, where {@see CompilerWarnings} suppresses
 * it, and the warning would silently only ever fire for a bare `def`.
 *
 * Detection only: the stdlib suppression and the per-`(file, subject)` dedup
 * belong to {@see CompilerWarnings}, which is why this class is stateless.
 *
 * @internal
 */
final class ReferShadowWarner
{
    private static ?self $instance = null;

    /**
     * Shared instance. Stateless, so it exists only to spare the analyzer a
     * per-def allocation; there is nothing to reset between runs.
     */
    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    /**
     * @param array<string, Symbol> $refers the refer table of `$ns`, keyed by referred name
     */
    public function maybeWarn(string $ns, Symbol $name, array $refers): void
    {
        $referredFrom = $refers[$name->getName()] ?? null;
        if (!$referredFrom instanceof Symbol) {
            return;
        }

        $location = $name->getStartLocation();
        if (!$location instanceof SourceLocation) {
            return;
        }

        CompilerWarnings::warnOnceForSource(
            $location->getFile(),
            $ns . '/' . $name->getName(),
            sprintf(
                // Clojure's wording, minus its "WARNING: " prefix: PHP already
                // prepends "Warning: " to an E_USER_WARNING.
                "%s already refers to: #'%s/%s in namespace: %s, being replaced by: #'%s/%s (at %s:%d)",
                $name->getName(),
                $referredFrom->getName(),
                $name->getName(),
                $ns,
                $ns,
                $name->getName(),
                $location->getFile(),
                $location->getLine(),
            ),
        );
    }
}
