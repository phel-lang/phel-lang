<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\Environment;

/**
 * On-demand loader for bundled `phel.*` namespaces that the REPL does not seed
 * eagerly. When the analyzer meets a fully qualified reference to a bundled
 * namespace that has not been loaded yet (`phel.json/encode`), the
 * {@see SymbolResolver} asks the registered resolver to load it before failing
 * with a "not defined" error.
 */
interface BundledNamespaceResolverInterface
{
    /**
     * Loads the given namespace (and its transitive closure) if it is a known
     * bundled namespace that is not loaded yet.
     *
     * @return bool true when the namespace was just loaded and resolution
     *              should be retried; false when nothing was loaded
     */
    public function resolveBundledNamespace(string $namespace): bool;
}
