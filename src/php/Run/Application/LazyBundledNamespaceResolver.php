<?php

declare(strict_types=1);

namespace Phel\Run\Application;

use Phel\Compiler\Domain\Analyzer\Environment\BundledNamespaceResolverInterface;
use Phel\Shared\Facade\BuildFacadeInterface;

use function in_array;
use function str_replace;

/**
 * Loads a bundled `phel.*` namespace and its transitive closure the first time
 * the REPL references it, so REPL boot only pays for `phel.core` while keeping
 * fully qualified references (`phel.json/encode`) resolvable on demand.
 */
final readonly class LazyBundledNamespaceResolver implements BundledNamespaceResolverInterface
{
    /**
     * @param list<string> $bundledNamespaces canonical (dot-separated) bundled namespace names
     * @param list<string> $srcDirectories
     */
    public function __construct(
        private BuildFacadeInterface $buildFacade,
        private array $bundledNamespaces,
        private array $srcDirectories,
        private NamespaceFileTracker $fileTracker,
    ) {}

    public function resolveBundledNamespace(string $namespace): bool
    {
        $canonical = str_replace('\\', '.', $namespace);
        if (!in_array($canonical, $this->bundledNamespaces, true)) {
            return false;
        }

        $loadedAny = false;
        foreach ($this->buildFacade->getDependenciesForNamespace($this->srcDirectories, [$canonical]) as $info) {
            $file = $info->getFile();
            if ($this->fileTracker->isLoaded($file)) {
                continue;
            }

            $this->buildFacade->evalFile($file);
            $this->fileTracker->markLoaded($file);
            $loadedAny = true;
        }

        return $loadedAny;
    }
}
