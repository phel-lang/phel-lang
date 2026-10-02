<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer;

use Phel\Compiler\Domain\Analyzer\SymbolSuggestionProvider;
use PHPUnit\Framework\TestCase;

use function count;

final class SymbolSuggestionProviderTest extends TestCase
{
    private SymbolSuggestionProvider $provider;

    protected function setUp(): void
    {
        $this->provider = new SymbolSuggestionProvider();
    }

    public function test_find_similar_with_typo(): void
    {
        $availableSymbols = ['print', 'println', 'printf', 'map', 'filter', 'reduce'];

        $suggestions = $this->provider->findSimilar('prnt', $availableSymbols);

        // Should suggest 'print' first (distance 1), then 'printf'/'println' (distance 2)
        self::assertSame('print', $suggestions[0]);
        self::assertContains('printf', $suggestions);
        self::assertContains('println', $suggestions);
    }

    public function test_find_similar_with_multiple_close_matches(): void
    {
        $availableSymbols = ['map', 'mop', 'mat', 'filter'];

        $suggestions = $this->provider->findSimilar('mp', $availableSymbols);

        // mp -> map (distance 1), mp -> mop (distance 1)
        self::assertContains('map', $suggestions);
    }

    public function test_find_similar_returns_empty_for_no_matches(): void
    {
        $availableSymbols = ['print', 'println', 'printf'];

        $suggestions = $this->provider->findSimilar('zzzzzzzzzzz', $availableSymbols);

        self::assertSame([], $suggestions);
    }

    public function test_find_similar_does_not_suggest_exact_match(): void
    {
        $availableSymbols = ['print', 'println'];

        $suggestions = $this->provider->findSimilar('print', $availableSymbols);

        self::assertNotContains('print', $suggestions);
    }

    public function test_find_similar_limits_suggestions(): void
    {
        $availableSymbols = ['map', 'mop', 'mat', 'max', 'may', 'mar'];

        $suggestions = $this->provider->findSimilar('ma', $availableSymbols);

        // Should return at most 3 suggestions
        self::assertLessThanOrEqual(3, count($suggestions));
    }

    public function test_find_similar_sorts_by_distance(): void
    {
        $availableSymbols = ['println', 'print', 'printf'];

        $suggestions = $this->provider->findSimilar('prnt', $availableSymbols);

        // print (distance 1) should come before println (distance 2)
        self::assertSame('print', $suggestions[0]);
    }

    public function test_find_similar_with_empty_available_symbols(): void
    {
        $suggestions = $this->provider->findSimilar('test', []);

        self::assertSame([], $suggestions);
    }

    public function test_find_similar_with_similar_function_names(): void
    {
        $availableSymbols = ['get-in', 'assoc-in', 'update-in', 'get'];

        $suggestions = $this->provider->findSimilar('getin', $availableSymbols);

        self::assertContains('get-in', $suggestions);
    }

    public function test_find_similar_handles_distance_threshold(): void
    {
        $availableSymbols = ['print', 'println', 'map', 'filter'];

        // 'xyzwvut' is too far from all these (more than 3 edits)
        $suggestions = $this->provider->findSimilar('xyzwvut', $availableSymbols);

        self::assertSame([], $suggestions);
    }

    public function test_find_similar_prefers_print_family_over_unrelated_short_words(): void
    {
        // Regression for #2637: `prn` used to surface `or`/`pop`/`put` (all
        // levenshtein distance 2) while `print`/`println` were absent.
        $availableSymbols = ['or', 'pop', 'put', 'print', 'println', 'printf', 'map'];

        $suggestions = $this->provider->findSimilar('prn', $availableSymbols);

        self::assertSame('print', $suggestions[0]);
        self::assertContains('println', $suggestions);
        self::assertNotContains('or', $suggestions);
        self::assertNotContains('pop', $suggestions);
        self::assertNotContains('put', $suggestions);
    }

    public function test_find_similar_includes_longer_subsequence_beyond_fixed_distance(): void
    {
        // `prn` -> `println` is levenshtein distance 4 (> MAX_EDIT_DISTANCE),
        // but `prn` is an in-order subsequence of `println`, so a length-scaled
        // bound keeps the good long match instead of dropping it.
        $suggestions = $this->provider->findSimilar('prn', ['println']);

        self::assertSame(['println'], $suggestions);
    }

    public function test_find_similar_breaks_distance_ties_by_shared_prefix(): void
    {
        // `ab` is a subsequence of both `yab` and `abx` at the same distance (1);
        // `abx` shares the leading prefix `ab` while `yab` shares none, so `abx`
        // ranks first regardless of input order.
        $suggestions = $this->provider->findSimilar('ab', ['yab', 'abx']);

        self::assertSame('abx', $suggestions[0]);
    }
}
