<?php

declare(strict_types=1);

namespace Phel\Run\Domain\Init;

/**
 * @internal
 */
final class NamespaceNormalizer
{
    /**
     * The `.main` suffix matches the `main.phel` entry module the scaffolder writes.
     */
    public function normalize(string $projectName): string
    {
        $clean = preg_replace('/[^a-zA-Z0-9]/', '', $projectName);

        return strtolower((string) $clean) . '.main';
    }
}
