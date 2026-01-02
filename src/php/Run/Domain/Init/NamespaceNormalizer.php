<?php

declare(strict_types=1);

namespace Phel\Run\Domain\Init;

final class NamespaceNormalizer
{
    /**
     * Convert a project name to a valid Phel namespace.
     *
     * - Removes all non-alphanumeric characters (hyphens, underscores, etc.)
     * - Converts to lowercase for consistency
     * - Appends \core as the default module
     */
    public function normalize(string $projectName): string
    {
        $clean = preg_replace('/[^a-zA-Z0-9]/', '', $projectName);

        return strtolower((string) $clean) . '\\core';
    }
}
