<?php

declare(strict_types=1);

namespace Phel\Api\Application;

use Phel\Api\Domain\SymbolMetadataFinderInterface;
use Phel\Shared\Api\PhelFunction;

use function str_starts_with;

/**
 * Resolves the one-line inline doc shown in the REPL on Tab for a completion
 * candidate: looks up its metadata and formats `<signature>: <summary>`.
 */
final readonly class CompletionDocResolver
{
    public function __construct(
        private SymbolMetadataFinderInterface $metadataFinder,
        private CompletionDocFormatter $formatter,
    ) {}

    public function resolve(string $candidate, string $currentNs = 'user'): ?string
    {
        // PHP-interop candidates (php/...) have no Phel metadata; skip them.
        if ($candidate === '' || str_starts_with($candidate, 'php/')) {
            return null;
        }

        $metadata = $this->metadataFinder->find($candidate, $currentNs);
        if (!$metadata instanceof PhelFunction) {
            return null;
        }

        return $this->formatter->format($metadata);
    }
}
