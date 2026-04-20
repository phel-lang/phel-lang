<?php

declare(strict_types=1);

namespace Phel\Watch\Domain;

/**
 * Resolves the fully-qualified Phel namespace defined by a given source file.
 */
interface NamespaceResolverInterface
{
    /**
     * Returns the fully-qualified namespace declared by the file (via `ns` or
     * `in-ns`) or `null` when the file does not declare one.
     */
    public function resolveFromFile(string $filePath): ?string;

    /**
     * Returns the namespace declared in the given source string, or `null`
     * when none is found.
     */
    public function resolveFromSource(string $source): ?string;
}
