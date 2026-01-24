<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer;

use function levenshtein;
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
     * @return array<string> List of suggested symbols, sorted by similarity
     */
    public function findSimilar(string $undefinedSymbol, array $availableSymbols): array
    {
        $suggestions = [];

        foreach ($availableSymbols as $candidate) {
            $distance = $this->calculateDistance($undefinedSymbol, $candidate);

            if ($distance <= self::MAX_EDIT_DISTANCE && $distance > 0) {
                $suggestions[] = [
                    'symbol' => $candidate,
                    'distance' => $distance,
                ];
            }
        }

        usort($suggestions, static fn (array $a, array $b): int => $a['distance'] <=> $b['distance']);

        $result = [];
        $count = 0;
        foreach ($suggestions as $suggestion) {
            if ($count >= self::MAX_SUGGESTIONS) {
                break;
            }

            $result[] = $suggestion['symbol'];
            ++$count;
        }

        return $result;
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
}
