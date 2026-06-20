<?php

declare(strict_types=1);

namespace Phel\Shared\Facade;

use Phel\Api\Transfer\CompletionResultTransfer;
use Phel\Api\Transfer\PhelFunction;

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
}
