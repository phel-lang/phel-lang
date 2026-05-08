<?php

declare(strict_types=1);

namespace Phel\Api\Application\Analysis;

use Phel\Api\Domain\AnalysisStageInterface;
use Phel\Shared\Facade\RunFacadeInterface;
use Throwable;

use function dirname;
use function is_file;

/**
 * Pre-load phel core and the project namespaces required (transitively) by
 * the file under analysis. Without this the analyzer reports false positives
 * (`PHEL001 Cannot resolve symbol`) for any aliased reference into a project
 * namespace, e.g. `c/step` where `(:require phelgeon\core :as c)`.
 *
 * Best-effort: any failure leaves the global env partially populated and
 * lets the following stages run, so legitimate diagnostics still surface.
 */
final readonly class PreloadDependenciesStage implements AnalysisStageInterface
{
    public function __construct(
        private RunFacadeInterface $runFacade,
    ) {}

    public function run(string $source, string $uri, array &$context): array
    {
        try {
            $this->runFacade->loadPhelNamespaces();
        } catch (Throwable) {
            return [];
        }

        if ($uri === '' || !is_file($uri)) {
            return [];
        }

        try {
            $namespace = $this->runFacade->getNamespaceFromFile($uri)->getNamespace();
        } catch (Throwable) {
            return [];
        }

        $directories = [
            dirname($uri),
            ...$this->runFacade->getAllPhelDirectories(),
        ];

        try {
            $deps = $this->runFacade->getDependenciesForNamespace($directories, [$namespace]);
        } catch (Throwable) {
            return [];
        }

        foreach ($deps as $dep) {
            if ($dep->getNamespace() === $namespace) {
                continue;
            }

            try {
                $this->runFacade->evalFile($dep);
            } catch (Throwable) {
                // Skip a single bad dep so the rest can still load.
            }
        }

        return [];
    }
}
