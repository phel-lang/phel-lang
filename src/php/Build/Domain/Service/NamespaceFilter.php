<?php

declare(strict_types=1);

namespace Phel\Build\Domain\Service;

use Phel\Build\Domain\Extractor\NamespaceInformation;

/**
 * Domain service for determining which namespaces should be ignored during build.
 * Pure domain logic with no I/O dependencies.
 */
final readonly class NamespaceFilter
{
    /**
     * @param list<string> $pathsToIgnore
     */
    public function __construct(
        private array $pathsToIgnore,
    ) {
    }

    /**
     * Determines if a namespace should be ignored based on its file path.
     */
    public function shouldIgnore(NamespaceInformation $info): bool
    {
        foreach ($this->pathsToIgnore as $path) {
            if (str_contains($info->getFile(), $path)) {
                return true;
            }
        }

        return false;
    }
}
