<?php

declare(strict_types=1);

namespace Phel\Run\Application;

use Phel\Build\BuildFacade;
use Phel\Lang\LoadClasspath;
use Phel\Shared\Facade\BuildFacadeInterface;
use Phel\Shared\Facade\CommandFacadeInterface;

/**
 * Orchestrates precompilation of the bundled `phel.*` stdlib into the
 * read-only, content-addressed bundle consumed at runtime. Discovers every
 * bundled namespace, resolves them in dependency order, and hands the ordered
 * source files to the build facade to compile.
 *
 * Invoked at distribution-build time (PHAR) so a cold `phel run` in any
 * project reuses the precompiled stdlib instead of recompiling it.
 */
final readonly class BundledStdlibPrecompiler
{
    public function __construct(
        private BuildFacadeInterface $buildFacade,
        private CommandFacadeInterface $commandFacade,
        private BundledNamespaces $bundledNamespaces,
    ) {}

    /**
     * @return int number of compiled files written
     */
    public function precompile(string $targetDir): int
    {
        $seeds = $this->bundledNamespaces->all();
        if ($seeds === []) {
            return 0;
        }

        $directories = $this->commandFacade->getAllPhelDirectories();
        $infos = $this->buildFacade->getDependenciesForNamespace($directories, $seeds);

        // Publish the load classpath so `(load ...)` inside a namespace resolves
        // its secondary files, and enable build mode like the regular runtime
        // loader. Evaluating each primary populates the compiled-code cache with
        // every file (primaries and secondaries) before we export the bundle.
        LoadClasspath::publish($directories);
        BuildFacade::enableBuildMode();
        try {
            foreach ($infos as $info) {
                $this->buildFacade->evalFile($info->getFile());
            }
        } finally {
            BuildFacade::disableBuildMode();
        }

        return $this->buildFacade->precompileBundledStdlib($targetDir);
    }
}
