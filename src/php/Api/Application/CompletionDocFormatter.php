<?php

declare(strict_types=1);

namespace Phel\Api\Application;

use Phel\Shared\Api\PhelFunction;

use function preg_replace;
use function rtrim;
use function strlen;
use function substr;
use function trim;

/**
 * Builds the one-line documentation string shown inline in the REPL when a
 * Tab completion resolves to a single candidate: `<signature>: <summary>`.
 */
final readonly class CompletionDocFormatter
{
    private const int MAX_SUMMARY = 100;

    public function format(PhelFunction $fn): ?string
    {
        $signature = trim($fn->signatures[0] ?? $fn->name);
        $summary = $this->firstLine($fn->description !== '' ? $fn->description : $fn->doc);

        if ($signature === '' && $summary === '') {
            return null;
        }

        if ($summary === '') {
            return $signature;
        }

        if ($signature === '') {
            return $summary;
        }

        return $signature . ': ' . $summary;
    }

    private function firstLine(string $text): string
    {
        // Drop markdown code-fence markers that appear in some `:doc` blocks.
        $withoutFences = (string) preg_replace('/```[a-z]*/', ' ', $text);
        $normalized = trim((string) preg_replace('/\s+/', ' ', $withoutFences));
        if ($normalized === '') {
            return '';
        }

        if (strlen($normalized) <= self::MAX_SUMMARY) {
            return $normalized;
        }

        return rtrim(substr($normalized, 0, self::MAX_SUMMARY)) . '...';
    }
}
