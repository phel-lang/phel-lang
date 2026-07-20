<?php

declare(strict_types=1);

namespace Phel\Api;

use Gacela\Framework\AbstractFacade;
use Phel\Api\Infrastructure\Daemon\ApiDaemon;
use Phel\Api\Transfer\Completion;
use Phel\Api\Transfer\Definition;
use Phel\Api\Transfer\Diagnostic;
use Phel\Api\Transfer\Location;
use Phel\Api\Transfer\ProjectIndex;
use Phel\Shared\Api\CompletionResultTransfer;
use Phel\Shared\Api\PhelFunction;
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
     * Extract the top-level definitions from a single source buffer. Used for
     * document symbols, where the in-memory (possibly unsaved) buffer is
     * authoritative and the filesystem index may be stale or unavailable.
     *
     * @return list<Definition>
     */
    public function extractDefinitions(string $source, string $uri): array
    {
        return $this->getFactory()
            ->createSymbolExtractor()
            ->definitionsOf($source, $uri);
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

    public function findSymbolMetadata(string $symbol, string $currentNs = 'user'): ?PhelFunction
    {
        return $this->getFactory()
            ->createSymbolMetadataFinder()
            ->find($symbol, $currentNs);
    }

    public function completionDoc(string $candidate, string $currentNs = 'user'): ?string
    {
        return $this->getFactory()
            ->createCompletionDocResolver()
            ->resolve($candidate, $currentNs);
    }

    /**
     * Markdown hover for the PHP-interop symbol under the cursor (method,
     * static member, global function, class), or null when not applicable.
     */
    public function phpInteropHoverAt(string $source, int $line, int $col): ?string
    {
        return $this->getFactory()
            ->createPhpInteropDocResolver()
            ->hoverAt($source, $line, $col);
    }

    /**
     * LSP SignatureHelp payload for the PHP-interop call enclosing the cursor,
     * or null when not applicable.
     *
     * @return array{signatures: list<array{label: string}>, activeSignature: int, activeParameter: int}|null
     */
    public function phpInteropSignatureAt(string $source, int $line, int $col): ?array
    {
        return $this->getFactory()
            ->createPhpInteropDocResolver()
            ->signatureAt($source, $line, $col);
    }

    /**
     * LSP SignatureHelp payload for the plain Phel function call enclosing the
     * cursor, or null when not applicable.
     *
     * @return array{signatures: list<array{label: string, parameters: list<array{label: string}>, documentation?: string}>, activeSignature: int, activeParameter: int}|null
     */
    public function phelSignatureAt(string $source, int $line, int $col, string $currentNs = 'user'): ?array
    {
        return $this->getFactory()
            ->createPhelSignatureResolver()
            ->signatureAt($source, $line, $col, $currentNs);
    }
}
