<?php

declare(strict_types=1);

namespace Phel\Build\Domain\Service;

use Phel\Build\Domain\Compile\BuildOptions;
use Phel\Build\Domain\Extractor\NamespaceInformation;
use Phel\Build\Domain\Port\FileSystem\FileSystemPort;

/**
 * Domain service for determining cache eligibility during builds.
 * Encapsulates the logic for deciding when cached files can be reused.
 */
final readonly class CacheEligibilityChecker
{
    /**
     * @param list<string> $pathsToAvoidCache
     */
    public function __construct(
        private FileSystemPort $fileSystem,
        private array $pathsToAvoidCache,
    ) {
    }

    /**
     * Determines if cached output can be used for the given namespace.
     */
    public function canUseCache(
        BuildOptions $buildOptions,
        string $targetFile,
        NamespaceInformation $info,
    ): bool {
        if (!$buildOptions->isCacheEnabled()) {
            return false;
        }

        if (!$this->fileSystem->exists($targetFile)) {
            return false;
        }

        if ($this->fileSystem->lastModified($targetFile) !== $this->fileSystem->lastModified($info->getFile())) {
            return false;
        }

        foreach ($this->pathsToAvoidCache as $path) {
            if (str_contains($targetFile, $path)) {
                return false;
            }
        }

        return true;
    }
}
