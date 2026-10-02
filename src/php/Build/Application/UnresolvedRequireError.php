<?php

declare(strict_types=1);

namespace Phel\Build\Application;

use RuntimeException;

/**
 * The error the emitted `ns` form throws for a require that matched no
 * source file, or null when the namespace still resolves without one.
 *
 * @internal
 */
final readonly class UnresolvedRequireError
{
    public function __construct(
        private BundledNamespaceIndex $bundledNamespaces,
        private MissingRequireReporter $missingRequireReporter,
    ) {}

    /**
     * @param list<string> $searchedDirectories
     */
    public function for(
        string $requiredNs,
        string $requiringNs,
        string $requiringFile,
        array $searchedDirectories,
    ): ?RuntimeException {
        $message = $this->bundledNamespaces->unresolvedRequireMessage($requiredNs, $requiringNs);
        if ($message === null) {
            return null;
        }

        return $this->missingRequireReporter->error($message, $requiredNs, $requiringFile, $searchedDirectories);
    }
}
