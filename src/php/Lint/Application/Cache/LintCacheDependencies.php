<?php

declare(strict_types=1);

namespace Phel\Lint\Application\Cache;

use Phel\Shared\Facade\RunFacadeInterface;
use Throwable;

use function dirname;

/**
 * @internal
 */
final readonly class LintCacheDependencies
{
    /**
     * @param list<string> $directories
     */
    public function __construct(
        private RunFacadeInterface $runFacade,
        private array $directories,
        private LintCacheLoadedSources $loadedSources,
    ) {}

    public function fingerprintFor(string $file): ?string
    {
        try {
            $namespace = $this->runFacade->getNamespaceFromFile($file);
            $dependencies = $namespace->getDependencies() === []
                ? []
                : $this->runFacade->getDependenciesForNamespace([dirname($file), ...$this->directories], $namespace->getDependencies());
        } catch (Throwable) {
            // A failed dependency walk cannot prove that a cached result is current.
            return null;
        }

        $hashes = [];
        foreach ([$namespace, ...$dependencies] as $dependency) {
            $sources = $this->loadedSources->hashesFor($dependency->getFile(), $dependency->getNamespace());
            if ($sources === null) {
                return null;
            }

            $hashes = [...$hashes, ...$sources];
        }

        ksort($hashes);

        return md5(serialize($hashes));
    }
}
