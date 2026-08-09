<?php

declare(strict_types=1);

namespace PhelTest\Unit\Api\Application;

use Phel\Api\Application\ArglistSignatureParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ArglistSignatureParserTest extends TestCase
{
    public function test_it_reads_a_single_arity_vector(): void
    {
        self::assertSame(
            ['(map f & colls)'],
            ArglistSignatureParser::parse('[f & colls]', 'map'),
        );
    }

    public function test_it_reads_every_arity_of_a_wrapped_multi_arity_list(): void
    {
        self::assertSame(
            ['(list)', '(list a)', '(list a b)', '(list a b c)', '(list a b c & more)'],
            ArglistSignatureParser::parse('([] [a] [a b] [a b c] [a b c & more])', 'list'),
        );
    }

    public function test_an_empty_parameter_vector_renders_the_bare_name(): void
    {
        self::assertSame(['(queue)'], ArglistSignatureParser::parse('[]', 'queue'));
    }

    /**
     * A destructuring parameter nests, so splitting on `] [` would read
     * `([[a b] c])` as two arities instead of one.
     */
    public function test_a_nested_destructuring_parameter_stays_one_arity(): void
    {
        self::assertSame(
            ['(f [a b] c)'],
            ArglistSignatureParser::parse('([[a b] c])', 'f'),
        );
    }

    public function test_it_keeps_nesting_across_several_arities(): void
    {
        self::assertSame(
            ['(f [a b])', '(f [a b] [c d])'],
            ArglistSignatureParser::parse('([[a b]] [[a b] [c d]])', 'f'),
        );
    }

    #[DataProvider('provideNothingToParse')]
    public function test_it_reports_nothing_when_there_is_nothing_to_read(string $arglists): void
    {
        self::assertSame([], ArglistSignatureParser::parse($arglists, 'anything'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideNothingToParse(): iterable
    {
        yield 'empty' => [''];
        yield 'blank' => ['   '];
        yield 'wrapped but empty' => ['()'];
        yield 'unbalanced open' => ['([a b)'];
        yield 'wrapped with no vector' => ['(a b)'];
    }

    /**
     * The caller keeps whatever fallback it had when the metadata is not a
     * shape this parser recognises, rather than publishing a mangled
     * signature.
     */
    public function test_an_unbalanced_arglist_is_not_guessed_at(): void
    {
        self::assertSame([], ArglistSignatureParser::parse('([] [a', 'list'));
    }

    public function test_surrounding_whitespace_is_ignored(): void
    {
        self::assertSame(
            ['(list)', '(list a)'],
            ArglistSignatureParser::parse('  ([]  [a])  ', 'list'),
        );
    }
}
