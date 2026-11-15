<?php

declare(strict_types=1);

namespace Phel\Run\Application;

use Phel\Shared\Facade\BuildFacadeInterface;
use Phel\Shared\Facade\CompilerFacadeInterface;
use Phel\Shared\NamespaceInformation;

/**
 * @internal
 */
final readonly class SourceAnalysisPreparer
{
    public function __construct(
        private BuildFacadeInterface $buildFacade,
        private CompilerFacadeInterface $compilerFacade,
        private string $stdlibDirectory,
    ) {}

    public function prepare(): void
    {
        clearstatcache(true);
        $this->buildFacade->refreshNamespaceScans();
        $stdlibNamespaces = array_map(
            static fn(NamespaceInformation $info): string => $info->getNamespace(),
            $this->buildFacade->getNamespaceFromDirectories([$this->stdlibDirectory]),
        );
        $this->compilerFacade->forgetProjectNamespaces($stdlibNamespaces);
        NamespaceFileTracker::retainDirectory($this->stdlibDirectory);
    }
}
