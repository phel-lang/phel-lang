<?php

declare(strict_types=1);

namespace Phel\Api;

use Gacela\Framework\AbstractFacade;
use Phel\Api\Infrastructure\Daemon\ApiDaemon;
use Phel\Api\Transfer\Completion;
use Phel\Api\Transfer\CompletionResultTransfer;
use Phel\Api\Transfer\Definition;
use Phel\Api\Transfer\Diagnostic;
use Phel\Api\Transfer\Location;
use Phel\Api\Transfer\PhelFunction;
use Phel\Api\Transfer\ProjectIndex;
use Phel\Shared\Facade\ApiFacadeInterface;

/**
 * @extends AbstractFacade<ApiFactory>
 */
final class ApiFacade extends AbstractFacade implements ApiFacadeInterface
{
    /**
     * @return list<string>
     */
    public function replComplete(string $input): array
    {
        return $this->getFactory()
            ->createReplCompleter()
            ->complete($input);
    }

    /**
     * Complete input with type annotations for nREPL clients.
     *
     * @return list<CompletionResultTransfer>
     */
    public function replCompleteWithTypes(string $input): array
    {
        return $this->getFactory()
            ->createReplCompleter()
            ->completeWithTypes($input);
    }

    /**
     * @param list<string> $namespaces
     *
     * @return list<PhelFunction>
     */
    public function getPhelFunctions(array $namespaces = []): array
    {
        return $this->getFactory()
            ->createPhelFnNormalizer()
            ->getPhelFunctions($namespaces);
    }

    /**
     * Run Parser + Analyzer without emit and return semantic diagnostics.
     *
     * @return list<Diagnostic>
     */
    public function analyzeSource(string $source, string $uri): array
    {
        return $this->getFactory()
            ->createSourceAnalyzer()
            ->analyze($source, $uri);
    }

    /**
     * Build a project-level symbol index from one or more source directories.
     *
     * @param list<string> $srcDirs
     */
    public function indexProject(array $srcDirs): ProjectIndex
    {
        return $this->getFactory()
            ->createProjectIndexer()
            ->index($srcDirs);
    }

    /**
     * Resolve a symbol to its defining site ("jump to definition").
     */
    public function resolveSymbol(ProjectIndex $index, string $namespace, string $symbol): ?Definition
    {
        return $this->getFactory()
            ->createSymbolResolver()
            ->resolve($index, $namespace, $symbol);
    }

    /**
     * Find reference sites of a given symbol.
     *
     * @return list<Location>
     */
    public function findReferences(ProjectIndex $index, string $namespace, string $symbol): array
    {
        return $this->getFactory()
            ->createReferenceFinder()
            ->find($index, $namespace, $symbol);
    }

    /**
     * Context-aware completion at a given point in source (locals + project defs + phel-core).
     *
     * @return list<Completion>
     */
    public function completeAtPoint(string $source, int $line, int $col, ProjectIndex $index): array
    {
        return $this->getFactory()
            ->createPointCompleter()
            ->completeAtPoint($source, $line, $col, $index);
    }

    public function createApiDaemon(): ApiDaemon
    {
        return $this->getFactory()->createApiDaemon($this);
    }
}
