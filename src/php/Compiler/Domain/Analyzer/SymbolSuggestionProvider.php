<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer;

use function array_map;
use function array_slice;
use function intdiv;
use function levenshtein;
use function max;
use function min;
use function strcmp;
use function strlen;
use function usort;

final class SymbolSuggestionProvider
{
    private const int MAX_EDIT_DISTANCE = 3;

    private const int MAX_SUGGESTIONS = 3;

    /**
     * Finds similar symbols to the given undefined symbol.
     *
     * @param string        $undefinedSymbol  The symbol that could not be resolved
     * @param array<string> $availableSymbols List of available symbols to search through
     *
     * @return array<string> List of suggested symbols, most relevant first
     */
    public function findSimilar(string $undefinedSymbol, array $availableSymbols): array
    {
        $scored = [];
        foreach ($availableSymbols as $candidate) {
            $score = $this->score($undefinedSymbol, $candidate);
            if ($score !== null) {
                $scored[] = $score;
            }
        }

        usort($scored, $this->byRelevance(...));

        return array_map(
            static fn(array $score): string => $score['symbol'],
            array_slice($scored, 0, self::MAX_SUGGESTIONS),
        );
    }

    /**
     * Scores a candidate against the typed symbol, or returns `null` when the
     * candidate is too dissimilar to suggest.
     *
     * @return array{symbol: string, subsequence: int, prefix: int, distance: int}|null
     */
    private function score(string $undefinedSymbol, string $candidate): ?array
    {
        $distance = $this->calculateDistance($undefinedSymbol, $candidate);
        if ($distance === 0) {
            // The candidate is the symbol itself; never suggest it.
            return null;
        }

        $maxLength = max(strlen($undefinedSymbol), strlen($candidate));
        $isSubsequence = $this->isSubsequence($undefinedSymbol, $candidate);

        // Small absolute edits always qualify. Longer candidates that contain
        // the typed symbol as an in-order subsequence (e.g. `prn` in `println`,
        // distance 4) qualify under a length-scaled bound, so a good long match
        // is not cut off by the fixed distance ceiling.
        $accepted = $distance <= self::MAX_EDIT_DISTANCE
            || ($isSubsequence && $distance <= intdiv($maxLength + 1, 2));
        if (!$accepted) {
            return null;
        }

        return [
            'symbol' => $candidate,
            'subsequence' => $isSubsequence ? 0 : 1,
            'prefix' => $this->commonPrefixLength($undefinedSymbol, $candidate),
            'distance' => $distance,
        ];
    }

    /**
     * Orders by, in priority: subsequence match (the typed symbol is an in-order
     * fragment of the candidate, e.g. `prn` in `print`), then longer shared
     * prefix, then smaller edit distance, then alphabetically for a stable,
     * deterministic result.
     *
     * @param array{symbol: string, subsequence: int, prefix: int, distance: int} $a
     * @param array{symbol: string, subsequence: int, prefix: int, distance: int} $b
     */
    private function byRelevance(array $a, array $b): int
    {
        return $a['subsequence'] <=> $b['subsequence']
            ?: $b['prefix'] <=> $a['prefix']
            ?: $a['distance'] <=> $b['distance']
            ?: strcmp($a['symbol'], $b['symbol']);
    }

    private function calculateDistance(string $undefinedSymbol, string $candidate): int
    {
        $undefinedLength = strlen($undefinedSymbol);
        $candidateLength = strlen($candidate);

        if ($undefinedLength > 255 || $candidateLength > 255) {
            return PHP_INT_MAX;
        }

        return levenshtein($undefinedSymbol, $candidate);
    }

    /**
     * Whether every character of `$needle` appears in `$haystack` in order
     * (not necessarily contiguously). `prn` is a subsequence of `println`.
     */
    private function isSubsequence(string $needle, string $haystack): bool
    {
        $needleLength = strlen($needle);
        $haystackLength = strlen($haystack);

        $i = 0;
        for ($j = 0; $i < $needleLength && $j < $haystackLength; ++$j) {
            if ($needle[$i] === $haystack[$j]) {
                ++$i;
            }
        }

        return $i === $needleLength;
    }

    private function commonPrefixLength(string $a, string $b): int
    {
        $limit = min(strlen($a), strlen($b));

        $length = 0;
        while ($length < $limit && $a[$length] === $b[$length]) {
            ++$length;
        }

        return $length;
    }
}
