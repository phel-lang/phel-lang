<?php

declare(strict_types=1);

namespace Phel\Run\Domain\Init;

use Phel\Config\ProjectLayout;

final class NamespaceNormalizer
{
    /**
     * Convert a project name to a valid Phel namespace.
     *
     * - Removes all non-alphanumeric characters (hyphens, underscores, etc.)
     * - Converts to lowercase for consistency
     * - Appends a module suffix matching the entry file: `\main` for the
     *   root layout (single `main.phel`), `\core` otherwise.
     */
    public function normalize(string $projectName, ProjectLayout $layout = ProjectLayout::Conventional): string
    {
        $clean = preg_replace('/[^a-zA-Z0-9]/', '', $projectName);
        $module = $layout === ProjectLayout::Root ? 'main' : 'core';

        return strtolower((string) $clean) . '\\' . $module;
    }
}
