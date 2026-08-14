<?php

declare(strict_types=1);

namespace Phel\Shared\Facade;

use Phel\Shared\Api\Completion;
use Phel\Shared\Api\CompletionResultTransfer;
use Phel\Shared\Api\Definition;
use Phel\Shared\Api\Diagnostic;
use Phel\Shared\Api\Location;
use Phel\Shared\Api\PhelFunction;
use Phel\Shared\Api\ProjectIndex;

/**
 * The semantic-analysis contract every editor-facing consumer talks to
 * (`Lint`, `Lsp`, `Nrepl`, `Run`, `Watch`).
 *
 * The LSP SignatureHelp wire shape is declared on the contract rather than on
 * the facade, so the contract, the facade and the resolvers behind it all
 * describe one payload.
 *
 * @phpstan-type SignatureParameter array{label: string}
 * @phpstan-type SignatureInformation array{label: string, parameters: list<SignatureParameter>, documentation?: string}
 * @phpstan-type SignatureHelp array{signatures: list<SignatureInformation>, activeSignature: int, activeParameter: int}
 */
interface ApiFacadeInterface
{
    /**
     * Get all public phel functions in the namespaces.
     *
     * @param list<string> $namespaces If empty then it will get all
     *
     * @return list<PhelFunction>
     */
    public function getPhelFunctions(array $namespaces = []): array;

    /**
     * @return list<string>
     */
    public function replComplete(string $input): array;

    /**
     * Complete input with type annotations for nREPL clients.
     *
     * @return list<CompletionResultTransfer>
     */
    public function replCompleteWithTypes(string $input): array;

    /**
     * Resolve a symbol against the runtime registry. Returns metadata for
     * session-defined definitions in addition to core/library functions.
     */
    public function findSymbolMetadata(string $symbol, string $currentNs = 'user'): ?PhelFunction;

    /**
     * One-line documentation (`<signature>: <summary>`) for a completion
     * candidate, shown inline in the REPL on Tab. Null when the candidate has
     * no Phel metadata (e.g. `php/...` interop names).
     */
    public function completionDoc(string $candidate, string $currentNs = 'user'): ?string;

    /**
     * Run Parser + Analyzer without emit and return semantic diagnostics.
     *
     * @return list<Diagnostic>
     */
    public function analyzeSource(string $source, string $uri): array;

    /**
     * Build a project-level symbol index from one or more source directories.
     *
     * @param list<string> $srcDirs
     */
    public function indexProject(array $srcDirs): ProjectIndex;

    /**
     * Extract the top-level definitions from a single source buffer. Used for
     * document symbols, where the in-memory (possibly unsaved) buffer is
     * authoritative and the filesystem index may be stale or unavailable.
     *
     * @return list<Definition>
     */
    public function extractDefinitions(string $source, string $uri): array;

    /**
     * Resolve a symbol to its defining site ("jump to definition").
     */
    public function resolveSymbol(ProjectIndex $index, string $namespace, string $symbol): ?Definition;

    /**
     * Find reference sites of a given symbol.
     *
     * @return list<Location>
     */
    public function findReferences(ProjectIndex $index, string $namespace, string $symbol): array;

    /**
     * Context-aware completion at a given point in source (locals + project defs + phel-core).
     *
     * @return list<Completion>
     */
    public function completeAtPoint(string $source, int $line, int $col, ProjectIndex $index): array;

    /**
     * Resolve a lexical binding at the given 1-based {line, column} to the
     * location of the symbol that introduced it, or null when no modelled
     * binder does.
     */
    public function resolveLocalBinding(string $source, string $uri, int $line, int $col, string $word): ?Location;

    /**
     * Markdown hover for the PHP-interop symbol under the cursor (method,
     * static member, global function, class), or null when not applicable.
     */
    public function phpInteropHoverAt(string $source, int $line, int $col): ?string;

    /**
     * LSP SignatureHelp payload for the PHP-interop call enclosing the cursor,
     * or null when not applicable.
     *
     * @return SignatureHelp|null
     */
    public function phpInteropSignatureAt(string $source, int $line, int $col): ?array;

    /**
     * LSP SignatureHelp payload for the plain Phel function call enclosing the
     * cursor, or null when not applicable.
     *
     * @return SignatureHelp|null
     */
    public function phelSignatureAt(string $source, int $line, int $col, string $currentNs = 'user'): ?array;
}
