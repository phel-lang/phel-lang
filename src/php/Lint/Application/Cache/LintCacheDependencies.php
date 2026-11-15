<?php

declare(strict_types=1);

namespace Phel\Lint\Application\Cache;

use Phel\Shared\Api\ProjectIndex;
use Phel\Shared\Facade\RunFacadeInterface;
use Phel\Shared\Munge;
use Throwable;

use function dirname;

/**
 * @internal
 */
final class LintCacheDependencies
{
    private ?ProjectIndex $projectIndex = null;

    /** @var array<string, array<string, string>> */
    private array $indexedSources = [];

    /**
     * @param list<string> $directories
     */
    public function __construct(
        private readonly RunFacadeInterface $runFacade,
        private readonly array $directories,
        private readonly LintCacheLoadedSources $loadedSources,
    ) {}

    public function fingerprintFor(string $file, ?ProjectIndex $projectIndex = null): ?string
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

        foreach ($this->indexedSourcesFor($file, $projectIndex) as $path => $namespaceName) {
            $sources = $this->loadedSources->hashesFor($path, $namespaceName);
            if ($sources === null) {
                return null;
            }

            $hashes = [...$hashes, ...$sources];
        }

        ksort($hashes);

        return md5(serialize($hashes));
    }

    /**
     * @return array<string, string>
     */
    public function indexedSourcesFor(string $file, ?ProjectIndex $projectIndex): array
    {
        if (!$projectIndex instanceof ProjectIndex) {
            return [];
        }

        if ($this->projectIndex !== $projectIndex) {
            $this->projectIndex = $projectIndex;
            $this->indexedSources = [];
            $definitions = [];
            foreach ($projectIndex->definitions as $name => $definition) {
                $definitions[Munge::canonicalNs($name)] = $definition;
            }

            foreach ($projectIndex->references as $name => $locations) {
                $definition = $definitions[Munge::canonicalNs($name)] ?? null;
                if ($definition === null) {
                    continue;
                }

                foreach ($locations as $location) {
                    $this->indexedSources[$location->uri][$definition->uri] = $definition->namespace;
                }
            }
        }

        return $this->indexedSources[$file] ?? [];
    }
}
