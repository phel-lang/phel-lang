<?php

declare(strict_types=1);

namespace PhelTest\Integration\Compiler\Reader;

use DateTimeImmutable;
use Phel;
use Phel\Compiler\Application\Lexer;
use Phel\Compiler\CompilerFacade;
use Phel\Compiler\Domain\Parser\ParserNode\NodeInterface;
use Phel\Compiler\Domain\Parser\ParserNode\TriviaNodeInterface;
use Phel\Compiler\Domain\Reader\Exceptions\ReaderException;
use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Keyword;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;
use Phel\Lang\TagHandlers\BuiltinTagHandlers;
use Phel\Lang\TagRegistry;
use Phel\Lang\TypeInterface;
use Phel\Shared\Facade\CompilerFacadeInterface;
use PHPUnit\Framework\TestCase;

use RuntimeException;

use function get_debug_type;
use function is_scalar;
use function sprintf;
use function strtoupper;

final class ReaderTest extends TestCase
{
    private CompilerFacadeInterface $compilerFacade;

    public static function setUpBeforeClass(): void
    {
        // Reset global environment and initalize a new empty environment.
        // We initialize the environment here because we don't want to let
        // others define the environment for us.
        GlobalEnvironmentSingleton::reset();
        GlobalEnvironmentSingleton::initialize();
        Phel::clear();
    }

    protected function setUp(): void
    {
        Phel::bootstrap(__DIR__);
        Symbol::resetGen();
        $this->compilerFacade = new CompilerFacade();
    }

    public function test_read_number(): void
    {
        self::assertSame(1, $this->read('1'));
        self::assertSame(10, $this->read('10'));
        self::assertSame(100, $this->read('100'));
        self::assertSame(10.0, $this->read('10.0'));
        self::assertSame(1.1, $this->read('1.1'));
        self::assertSame(10.11, $this->read('10.11'));
        self::assertSame(1337, $this->read('0x539'));
        self::assertSame(1337, $this->read('0x5_3_9'));
        self::assertSame(1337, $this->read('02471'));
        self::assertSame(1337, $this->read('024_71'));
        self::assertSame(1337, $this->read('0b10100111001'));
        self::assertSame(1337, $this->read('0b0101_0011_1001'));
        self::assertSame(1337.0, $this->read('1337e0'));
        self::assertSame(-1337, $this->read('-1337'));
        self::assertSame(-1337.0, $this->read('-1337.0'));
        self::assertSame(1337, $this->read('+1337'));
        self::assertSame(1337.0, $this->read('+1337.0'));
        self::assertSame(1.2e3, $this->read('1.2e3'));
        self::assertSame(7E-10, $this->read('7E-10'));
    }

    public function test_read_keyword(): void
    {
        self::assertEquals(
            $this->loc(Keyword::create('test'), 1, 0, 1, 5),
            $this->read(':test'),
        );
    }

    public function test_read_boolean(): void
    {
        self::assertTrue($this->read('true'));
        self::assertFalse($this->read('false'));
    }

    public function test_read_nil(): void
    {
        self::assertNull(
            $this->read('nil'),
        );
    }

    public function test_read_symbol(): void
    {
        self::assertEquals(
            $this->loc(Symbol::create('test'), 1, 0, 1, 4),
            $this->read('test'),
        );
    }

    public function test_read_list(): void
    {
        self::assertEquals(
            $this->loc(Phel::list(), 1, 0, 1, 2),
            $this->read('()'),
        );
        self::assertEquals(
            $this->loc(Phel::list([
                $this->loc(Phel::list(), 1, 1, 1, 3),
            ]), 1, 0, 1, 4),
            $this->read('(())'),
        );

        self::assertEquals(
            $this->loc(Phel::list([
                $this->loc(Symbol::create('a'), 1, 1, 1, 2),
            ]), 1, 0, 1, 3),
            $this->read('(a)'),
        );

        self::assertEquals(
            $this->loc(Phel::list([
                $this->loc(Symbol::create('a'), 1, 1, 1, 2),
                $this->loc(Symbol::create('b'), 1, 3, 1, 4),
            ]), 1, 0, 1, 5),
            $this->read('(a b)'),
        );
    }

    public function test_read_vector(): void
    {
        self::assertEquals(
            $this->loc(Phel::vector(), 1, 0, 1, 2),
            $this->read('[]'),
        );
        self::assertEquals(
            $this->loc(Phel::vector([
                $this->loc(Phel::vector(), 1, 1, 1, 3),
            ]), 1, 0, 1, 4),
            $this->read('[[]]'),
        );

        self::assertEquals(
            $this->loc(Phel::vector([
                $this->loc(Symbol::create('a'), 1, 1, 1, 2),
            ]), 1, 0, 1, 3),
            $this->read('[a]'),
        );

        self::assertEquals(
            $this->loc(Phel::vector([
                $this->loc(Symbol::create('a'), 1, 1, 1, 2),
                $this->loc(Symbol::create('b'), 1, 3, 1, 4),
            ]), 1, 0, 1, 5),
            $this->read('[a b]'),
        );
    }

    public function test_read_empty_set(): void
    {
        self::assertEquals(
            $this->loc(Phel::set(), 1, 0, 1, 3),
            $this->read('#{}'),
        );
    }

    public function test_read_set(): void
    {
        self::assertEquals(
            $this->loc(Phel::set([
                $this->loc(Symbol::create('a'), 1, 2, 1, 3),
                $this->loc(Symbol::create('b'), 1, 4, 1, 5),
            ]), 1, 0, 1, 6),
            $this->read('#{a b}'),
        );
    }

    public function test_read_inline_comment(): void
    {
        self::assertEquals(
            $this->loc(Phel::list([
                $this->loc(Symbol::create('a'), 1, 1, 1, 2),
                $this->loc(Symbol::create('c'), 1, 7, 1, 8),
            ]), 1, 0, 1, 9),
            $this->read('(a #_b c)'),
        );
    }

    public function test_read_multiline_comment(): void
    {
        self::assertEquals(
            $this->loc(Phel::list([
                $this->loc(Symbol::create('a'), 1, 1, 1, 2),
                $this->loc(Symbol::create('c'), 1, 9, 1, 10),
            ]), 1, 0, 1, 11),
            $this->read('(a #|b|# c)'),
        );
    }

    public function test_read_nested_multiline_comment(): void
    {
        self::assertEquals(
            $this->loc(Phel::list([
                $this->loc(Symbol::create('a'), 1, 1, 1, 2),
                $this->loc(Symbol::create('e'), 1, 17, 1, 18),
            ]), 1, 0, 1, 19),
            $this->read('(a #|b #|c|# d|# e)'),
        );
    }

    public function test_quote(): void
    {
        self::assertEquals(
            $this->loc(Phel::list([
                Symbol::create(Symbol::NAME_QUOTE),
                $this->loc(Symbol::create('a'), 1, 1, 1, 2),
            ]), 1, 0, 1, 2),
            $this->read("'a"),
        );
    }

    public function test_deref(): void
    {
        self::assertEquals(
            $this->loc(Phel::list([
                Symbol::create(Symbol::NAME_DEREF),
                $this->loc(Symbol::create('a'), 1, 1, 1, 2),
            ]), 1, 0, 1, 2),
            $this->read('@a'),
        );
    }

    public function test_var_quote_reads_as_bare_symbol(): void
    {
        // `#'bar` parses cleanly and reads as the bare symbol `bar`.
        // Phel has no first-class Var type, so the `#'` prefix is accepted
        // for Clojure source compatibility but is transparent at read time.
        self::assertEquals(
            $this->loc(Symbol::create('bar'), 1, 2, 1, 5),
            $this->read("#'bar"),
        );
    }

    public function test_var_quote_inside_list(): void
    {
        // Regression: `(var? #'bar)` must not hit "Unterminated list (EOF)".
        self::assertEquals(
            $this->loc(Phel::list([
                $this->loc(Symbol::create('var?'), 1, 1, 1, 5),
                $this->loc(Symbol::create('bar'), 1, 8, 1, 11),
            ]), 1, 0, 1, 12),
            $this->read("(var? #'bar)"),
        );
    }

    public function test_comma_outside_quasiquote_is_ignored(): void
    {
        self::assertEquals(
            $this->loc(Symbol::create('a'), 1, 1, 1, 2),
            $this->read(',a'),
        );
    }

    public function test_comma_splice_outside_quasiquote_is_ignored(): void
    {
        self::assertEquals(
            $this->loc(Symbol::create('a'), 1, 2, 1, 3),
            $this->read(',@a'),
        );
    }

    public function test_tilde_outside_quasiquote_is_ignored(): void
    {
        self::assertEquals(
            $this->loc(Symbol::create('a'), 1, 1, 1, 2),
            $this->read('~a'),
        );
    }

    public function test_tilde_splice_outside_quasiquote_is_ignored(): void
    {
        self::assertEquals(
            $this->loc(Symbol::create('a'), 1, 2, 1, 3),
            $this->read('~@a'),
        );
    }

    public function test_quasiquote1(): void
    {
        self::assertEquals(
            $this->loc(Phel::list([
                $this->loc(Symbol::create(Symbol::NAME_QUOTE), 1, 1, 1, 8),
                $this->loc(Symbol::create(Symbol::NAME_UNQUOTE), 1, 1, 1, 8),
            ]), 1, 0, 1, 8),
            $this->read(sprintf('`%s', Symbol::NAME_UNQUOTE)),
        );
    }

    public function test_quasiquote2(): void
    {
        self::assertEquals(
            $this->loc(Phel::list([
                $this->loc(Symbol::create(Symbol::NAME_QUOTE), 1, 1, 1, 2),
                $this->loc(Symbol::create('a'), 1, 1, 1, 2),
            ]), 1, 0, 1, 2),
            $this->read('`a'),
        );
    }

    public function test_quasiquote3(): void
    {
        $l1 = $this->read('(apply list (concat (list (quote foo)) (list bar)))', true);
        $l2 = $this->read('`(foo ,bar)', true);
        self::assertTrue($l1->equals($l2));
    }

    public function test_quasiquote4(): void
    {
        $l1 = $this->read("'a", true);
        $l2 = $this->read('``,a', true);
        self::assertTrue($l1->equals($l2));
    }

    public function test_quasiquote5(): void
    {
        $l1 = $this->read('(apply list (concat (list (quote foo)) bar))', true);
        $l2 = $this->read('`(foo ,@bar)', true);
        self::assertTrue($l1->equals($l2));
    }

    public function test_quasiquote6(): void
    {
        $l1 = $this->read('(apply list (concat (list foo) bar))', true);
        $l2 = $this->read('`(,foo ,@bar)', true);
        self::assertTrue($l1->equals($l2));
    }

    public function test_quasiquote7(): void
    {
        $l1 = $this->read('(apply list (concat foo bar))', true);
        $l2 = $this->read('`(,@foo ,@bar)', true);
        self::assertTrue($l1->equals($l2));
    }

    public function test_quasiquote8(): void
    {
        $l1 = $this->read('(apply list (concat foo bar (list 1) (list "string") (list :keyword) (list true) (list nil)))', true);
        $l2 = $this->read('`(,@foo ,@bar 1 "string" :keyword true nil)', true);
        self::assertTrue($l1->equals($l2));
    }

    public function test_quasiquote_with_tilde_unquote(): void
    {
        $comma = $this->read('`(foo ,bar)', true);
        $tilde = $this->read('`(foo ~bar)', true);
        self::assertTrue($comma->equals($tilde));
    }

    public function test_quasiquote_with_tilde_unquote_splicing(): void
    {
        $comma = $this->read('`(foo ,@bar)', true);
        $tilde = $this->read('`(foo ~@bar)', true);
        self::assertTrue($comma->equals($tilde));
    }

    public function test_quasiquote_map_with_tilde_quote_unquote(): void
    {
        $comma = $this->read("`{:actual ,'error}", true);
        $tilde = $this->read("`{:actual ~'error}", true);
        self::assertTrue($comma->equals($tilde));
    }

    public function test_quasiquote_map_matches_issue_1201_shape(): void
    {
        // Faithful reproduction of the `success-opts#` map from issue #1201:
        // a quasiquoted map containing multiple `'~x` entries plus a `~'sym`.
        // The original bug surfaced as "Maps must have an even number of
        // parameters" because the lexer did not recognise `~` as unquote.
        $commaSrc = "`{:type :pass :message ',msg :expected ',form :actual ,'error}";
        $tildeSrc = "`{:type :pass :message '~msg :expected '~form :actual ~'error}";
        self::assertTrue($this->read($commaSrc, true)->equals($this->read($tildeSrc, true)));
    }

    public function test_quasiquote_auto_gensym(): void
    {
        $l1 = $this->read('(apply list (concat (list (quote foo__1)) (list (quote bar__2))))', true);
        $l2 = $this->read('`(foo$ bar$)', true);
        self::assertTrue($l1->equals($l2));
    }

    public function test_quasiquote_auto_gensym_cached_value(): void
    {
        $l1 = $this->read('(apply list (concat (list (quote foo__1)) (list (quote foo__1))))', true);
        $l2 = $this->read('`(foo$ foo$)', true);
        self::assertTrue($l1->equals($l2));
    }

    public function test_quasiquote_auto_gensym_mixed_values(): void
    {
        $l1 = $this->read('(apply list (concat (list (quote foo__1)) (list (quote bar__2)) (list (quote foo__1))))', true);
        $l2 = $this->read('`(foo$ bar$ foo$)', true);
        self::assertTrue($l1->equals($l2));
    }

    public function test_quasiquote_auto_gensym_hash_suffix(): void
    {
        $l1 = $this->read('(apply list (concat (list (quote foo__1)) (list (quote bar__2))))', true);
        $l2 = $this->read('`(foo# bar#)', true);
        self::assertTrue($l1->equals($l2));
    }

    public function test_quasiquote_auto_gensym_hash_suffix_cached_value(): void
    {
        $l1 = $this->read('(apply list (concat (list (quote foo__1)) (list (quote foo__1))))', true);
        $l2 = $this->read('`(foo# foo#)', true);
        self::assertTrue($l1->equals($l2));
    }

    public function test_quasiquote_dollar_auto_gensym_emits_deprecation(): void
    {
        $warning = null;
        set_error_handler(static function (int $errno, string $errstr) use (&$warning): bool {
            $warning = $errstr;
            return true;
        }, E_USER_DEPRECATED);

        try {
            $this->read('`(foo$)', true);
        } finally {
            restore_error_handler();
        }

        self::assertNotNull($warning);
        self::assertStringContainsString('"foo$"', $warning);
        self::assertStringContainsString('"foo#"', $warning);
    }

    public function test_quasiquote_hash_auto_gensym_does_not_emit_deprecation(): void
    {
        $warning = null;
        set_error_handler(static function (int $errno, string $errstr) use (&$warning): bool {
            $warning = $errstr;
            return true;
        }, E_USER_DEPRECATED);

        try {
            $this->read('`(foo#)', true);
        } finally {
            restore_error_handler();
        }

        self::assertNull($warning);
    }

    public function test_read_string(): void
    {
        self::assertSame(
            'abc',
            $this->read('"abc"'),
        );

        self::assertSame(
            'ab"c',
            $this->read('"ab\"c"'),
        );

        self::assertSame(
            "\\\r\n\t\f\v\e\$",
            $this->read('"\\\\\r\n\t\f\v\e\$"'),
        );

        self::assertSame(
            'read $abc sign',
            $this->read('"read $abc sign"'),
        );

        self::assertSame(
            "\x41",
            $this->read('"\x41"'),
        );

        self::assertSame(
            "\u{65}",
            $this->read('"\u{65}"'),
        );

        self::assertSame(
            "\u{129}",
            $this->read('"\u{129}"'),
        );

        self::assertSame(
            "\u{1000}",
            $this->read('"\u{1000}"'),
        );

        self::assertSame(
            "\u{10000}",
            $this->read('"\u{10000}"'),
        );

        self::assertSame(
            "\77",
            $this->read('"\77"'),
        );
    }

    public function test_read_empty_map(): void
    {
        self::assertEquals(
            $this->loc(Phel::map(), 1, 0, 1, 2),
            $this->read('{}'),
        );
    }

    public function test_map_table1(): void
    {
        self::assertEquals(
            $this->loc(
                Phel::map(
                    $this->loc(Keyword::create('a'), 1, 1, 1, 3),
                    1,
                ),
                1,
                0,
                1,
                6,
            ),
            $this->read('{:a 1}'),
        );
    }

    public function test_map_table2(): void
    {
        self::assertEquals(
            $this->loc(Phel::map(
                $this->loc(Keyword::create('a'), 1, 1, 1, 3),
                1,
                $this->loc(Keyword::create('b'), 1, 6, 1, 8),
                2,
            ), 1, 0, 1, 11),
            $this->read('{:a 1 :b 2}'),
        );
    }

    public function test_map_with_commas(): void
    {
        self::assertEquals(
            $this->loc(Phel::map(
                $this->loc(Keyword::create('a'), 1, 1, 1, 3),
                1,
                $this->loc(Keyword::create('b'), 1, 7, 1, 9),
                2,
            ), 1, 0, 1, 12),
            $this->read('{:a 1, :b 2}'),
        );
    }

    public function test_map_uneven(): void
    {
        $this->expectException(ReaderException::class);
        $this->read('{:a}');
    }

    public function test_meta_keyword(): void
    {
        self::assertEquals(
            $this->loc(
                $this->withMeta(
                    Symbol::create('test'),
                    Phel::map(
                        $this->loc(Keyword::create('test'), 1, 1, 1, 6),
                        true,
                    ),
                ),
                1,
                7,
                1,
                11,
            ),
            $this->read('^:test test'),
        );
    }

    public function test_meta_string(): void
    {
        self::assertEquals(
            $this->loc(
                $this->withMeta(
                    Symbol::create('test'),
                    Phel::map(
                        Keyword::create('tag'),
                        'test',
                    ),
                ),
                1,
                8,
                1,
                12,
            ),
            $this->read('^"test" test'),
        );
    }

    public function test_meta_symbol(): void
    {
        self::assertEquals(
            $this->loc(
                $this->withMeta(
                    Symbol::create('test'),
                    Phel::map(
                        Keyword::create('tag'),
                        $this->loc(Symbol::create('String'), 1, 1, 1, 7),
                    ),
                ),
                1,
                8,
                1,
                12,
            ),
            $this->read('^String test'),
        );
    }

    public function test_meta_table(): void
    {
        self::assertEquals(
            $this->loc(
                $this->withMeta(
                    Symbol::create('test'),
                    Phel::map(
                        $this->loc(Keyword::create('a'), 1, 2, 1, 4),
                        1,
                        $this->loc(Keyword::create('b'), 1, 7, 1, 9),
                        2,
                    ),
                ),
                1,
                13,
                1,
                17,
            ),
            $this->read('^{:a 1 :b 2} test'),
        );
    }

    public function test_concat_meta(): void
    {
        self::assertEquals(
            $this->loc(
                $this->withMeta(
                    Symbol::create('test'),
                    Phel::map(
                        $this->loc(Keyword::create('b'), 1, 5, 1, 7),
                        true,
                        $this->loc(Keyword::create('a'), 1, 1, 1, 3),
                        true,
                    ),
                ),
                1,
                8,
                1,
                12,
            ),
            $this->read('^:a ^:b test'),
        );
    }

    public function test_vector_meta(): void
    {
        $this->expectException(ReaderException::class);
        $this->expectExceptionMessage('Metadata must be a Symbol, String, Keyword or Map');
        $this->read('^[:a] test');
    }

    public function test_meta_on_string(): void
    {
        $this->expectException(ReaderException::class);
        $this->expectExceptionMessage('Metadata can only applied to classes that implement MetaInterface');
        $this->read('^a "test"');
    }

    public function test_read_short_fn_zero_args(): void
    {
        self::assertEquals(
            $this->loc(
                Phel::list([
                    Symbol::create(Symbol::NAME_FN),
                    Phel::vector(),
                    $this->loc(
                        Phel::list([
                            $this->loc(Symbol::create('add'), 1, 2, 1, 5),
                        ]),
                        1,
                        0,
                        1,
                        6,
                    ),
                ]),
                1,
                0,
                1,
                6,
            ),
            $this->read('|(add)'),
        );
    }

    public function test_read_short_fn_one_arg(): void
    {
        self::assertEquals(
            $this->loc(
                Phel::list([
                    Symbol::create(Symbol::NAME_FN),
                    Phel::vector([
                        Symbol::create('__short_fn_1_1'),
                    ]),
                    $this->loc(
                        Phel::list([
                            $this->loc(Symbol::create('add'), 1, 2, 1, 5),
                            $this->loc(Symbol::create('__short_fn_1_1'), 1, 6, 1, 7),
                        ]),
                        1,
                        0,
                        1,
                        8,
                    ),
                ]),
                1,
                0,
                1,
                8,
            ),
            $this->read('|(add $)'),
        );
    }

    public function test_read_short_fn_one_arg_two_times(): void
    {
        self::assertEquals(
            $this->loc(
                Phel::list([
                    Symbol::create(Symbol::NAME_FN),
                    Phel::vector([
                        Symbol::create('__short_fn_1_1'),
                    ]),
                    $this->loc(
                        Phel::list([
                            $this->loc(Symbol::create('add'), 1, 2, 1, 5),
                            $this->loc(Symbol::create('__short_fn_1_1'), 1, 6, 1, 7),
                            $this->loc(Symbol::create('__short_fn_1_1'), 1, 8, 1, 9),
                        ]),
                        1,
                        0,
                        1,
                        10,
                    ),
                ]),
                1,
                0,
                1,
                10,
            ),
            $this->read('|(add $ $)'),
        );
    }

    public function test_read_short_fn_two_arguments(): void
    {
        self::assertEquals(
            $this->loc(
                Phel::list([
                    Symbol::create(Symbol::NAME_FN),
                    Phel::vector([
                        Symbol::create('__short_fn_1_1'),
                        Symbol::create('__short_fn_2_2'),
                    ]),
                    $this->loc(
                        Phel::list([
                            $this->loc(Symbol::create('add'), 1, 2, 1, 5),
                            $this->loc(Symbol::create('__short_fn_1_1'), 1, 6, 1, 8),
                            $this->loc(Symbol::create('__short_fn_2_2'), 1, 9, 1, 11),
                        ]),
                        1,
                        0,
                        1,
                        12,
                    ),
                ]),
                1,
                0,
                1,
                12,
            ),
            $this->read('|(add $1 $2)'),
        );
    }

    public function test_read_short_fn_arguments_twice(): void
    {
        self::assertEquals(
            $this->loc(
                Phel::list([
                    Symbol::create(Symbol::NAME_FN),
                    Phel::vector([
                        Symbol::create('__short_fn_1_1'),
                    ]),
                    $this->loc(
                        Phel::list([
                            $this->loc(Symbol::create('add'), 1, 2, 1, 5),
                            $this->loc(Symbol::create('__short_fn_1_1'), 1, 6, 1, 8),
                            $this->loc(Symbol::create('__short_fn_1_1'), 1, 9, 1, 11),
                        ]),
                        1,
                        0,
                        1,
                        12,
                    ),
                ]),
                1,
                0,
                1,
                12,
            ),
            $this->read('|(add $1 $1)'),
        );
    }

    public function test_read_short_fn_missing_argument(): void
    {
        self::assertEquals(
            $this->loc(
                Phel::list([
                    Symbol::create(Symbol::NAME_FN),
                    Phel::vector([
                        Symbol::create('__short_fn_1_1'),
                        Symbol::create('__short_fn_undefined_3'),
                        Symbol::create('__short_fn_3_2'),
                    ]),
                    $this->loc(
                        Phel::list([
                            $this->loc(Symbol::create('add'), 1, 2, 1, 5),
                            $this->loc(Symbol::create('__short_fn_1_1'), 1, 6, 1, 8),
                            $this->loc(Symbol::create('__short_fn_3_2'), 1, 9, 1, 11),
                        ]),
                        1,
                        0,
                        1,
                        12,
                    ),
                ]),
                1,
                0,
                1,
                12,
            ),
            $this->read('|(add $1 $3)'),
        );
    }

    public function test_read_short_fn_rest_arguments(): void
    {
        self::assertEquals(
            $this->loc(
                Phel::list([
                    Symbol::create(Symbol::NAME_FN),
                    Phel::vector([
                        Symbol::create('__short_fn_1_1'),
                        Symbol::create('&'),
                        Symbol::create('__short_fn_rest_2'),
                    ]),
                    $this->loc(
                        Phel::list([
                            $this->loc(Symbol::create('add'), 1, 2, 1, 5),
                            $this->loc(Symbol::create('__short_fn_1_1'), 1, 6, 1, 8),
                            $this->loc(Symbol::create('__short_fn_rest_2'), 1, 9, 1, 11),
                        ]),
                        1,
                        0,
                        1,
                        12,
                    ),
                ]),
                1,
                0,
                1,
                12,
            ),
            $this->read('|(add $1 $&)'),
        );
    }

    public function test_short_fn_rest_argument_multiple_times(): void
    {
        $this->assertEquals(
            $this->loc(
                Phel::list([
                    Symbol::create(Symbol::NAME_FN),
                    Phel::vector([
                        Symbol::create('&'),
                        Symbol::create('__short_fn_rest_1'),
                    ]),
                    $this->loc(
                        Phel::list([
                            $this->loc(Symbol::create('concat'), 1, 2, 1, 8),
                            $this->loc(Symbol::create('__short_fn_rest_1'), 1, 9, 1, 11),
                            $this->loc(Symbol::create('__short_fn_rest_1'), 1, 12, 1, 14),
                        ]),
                        1,
                        0,
                        1,
                        15,
                    ),
                ]),
                1,
                0,
                1,
                15,
            ),
            $this->read('|(concat $& $&)'),
        );
    }

    public function test_read_hash_fn_zero_args(): void
    {
        self::assertEquals(
            $this->loc(
                Phel::list([
                    Symbol::create(Symbol::NAME_FN),
                    Phel::vector(),
                    $this->loc(
                        Phel::list([
                            $this->loc(Symbol::create('add'), 1, 2, 1, 5),
                        ]),
                        1,
                        0,
                        1,
                        6,
                    ),
                ]),
                1,
                0,
                1,
                6,
            ),
            $this->read('#(add)'),
        );
    }

    public function test_read_hash_fn_one_arg(): void
    {
        self::assertEquals(
            $this->loc(
                Phel::list([
                    Symbol::create(Symbol::NAME_FN),
                    Phel::vector([
                        Symbol::create('__short_fn_1_1'),
                    ]),
                    $this->loc(
                        Phel::list([
                            $this->loc(Symbol::create('add'), 1, 2, 1, 5),
                            $this->loc(Symbol::create('__short_fn_1_1'), 1, 6, 1, 7),
                        ]),
                        1,
                        0,
                        1,
                        8,
                    ),
                ]),
                1,
                0,
                1,
                8,
            ),
            $this->read('#(add %)'),
        );
    }

    public function test_read_hash_fn_two_args(): void
    {
        self::assertEquals(
            $this->loc(
                Phel::list([
                    Symbol::create(Symbol::NAME_FN),
                    Phel::vector([
                        Symbol::create('__short_fn_1_1'),
                        Symbol::create('__short_fn_2_2'),
                    ]),
                    $this->loc(
                        Phel::list([
                            $this->loc(Symbol::create('add'), 1, 2, 1, 5),
                            $this->loc(Symbol::create('__short_fn_1_1'), 1, 6, 1, 8),
                            $this->loc(Symbol::create('__short_fn_2_2'), 1, 9, 1, 11),
                        ]),
                        1,
                        0,
                        1,
                        12,
                    ),
                ]),
                1,
                0,
                1,
                12,
            ),
            $this->read('#(add %1 %2)'),
        );
    }

    public function test_read_hash_fn_rest_args(): void
    {
        self::assertEquals(
            $this->loc(
                Phel::list([
                    Symbol::create(Symbol::NAME_FN),
                    Phel::vector([
                        Symbol::create('__short_fn_1_1'),
                        Symbol::create('&'),
                        Symbol::create('__short_fn_rest_2'),
                    ]),
                    $this->loc(
                        Phel::list([
                            $this->loc(Symbol::create('add'), 1, 2, 1, 5),
                            $this->loc(Symbol::create('__short_fn_1_1'), 1, 6, 1, 8),
                            $this->loc(Symbol::create('__short_fn_rest_2'), 1, 9, 1, 11),
                        ]),
                        1,
                        0,
                        1,
                        12,
                    ),
                ]),
                1,
                0,
                1,
                12,
            ),
            $this->read('#(add %1 %&)'),
        );
    }

    public function test_hash_fn_percent_used_twice(): void
    {
        self::assertEquals(
            $this->loc(
                Phel::list([
                    Symbol::create(Symbol::NAME_FN),
                    Phel::vector([
                        Symbol::create('__short_fn_1_1'),
                    ]),
                    $this->loc(
                        Phel::list([
                            $this->loc(Symbol::create('add'), 1, 2, 1, 5),
                            $this->loc(Symbol::create('__short_fn_1_1'), 1, 6, 1, 7),
                            $this->loc(Symbol::create('__short_fn_1_1'), 1, 8, 1, 9),
                        ]),
                        1,
                        0,
                        1,
                        10,
                    ),
                ]),
                1,
                0,
                1,
                10,
            ),
            $this->read('#(add % %)'),
        );
    }

    public function test_hash_fn_numbered_percent_used_twice(): void
    {
        self::assertEquals(
            $this->loc(
                Phel::list([
                    Symbol::create(Symbol::NAME_FN),
                    Phel::vector([
                        Symbol::create('__short_fn_1_1'),
                    ]),
                    $this->loc(
                        Phel::list([
                            $this->loc(Symbol::create('add'), 1, 2, 1, 5),
                            $this->loc(Symbol::create('__short_fn_1_1'), 1, 6, 1, 8),
                            $this->loc(Symbol::create('__short_fn_1_1'), 1, 9, 1, 11),
                        ]),
                        1,
                        0,
                        1,
                        12,
                    ),
                ]),
                1,
                0,
                1,
                12,
            ),
            $this->read('#(add %1 %1)'),
        );
    }

    public function test_hash_fn_missing_argument(): void
    {
        self::assertEquals(
            $this->loc(
                Phel::list([
                    Symbol::create(Symbol::NAME_FN),
                    Phel::vector([
                        Symbol::create('__short_fn_1_1'),
                        Symbol::create('__short_fn_undefined_3'),
                        Symbol::create('__short_fn_3_2'),
                    ]),
                    $this->loc(
                        Phel::list([
                            $this->loc(Symbol::create('add'), 1, 2, 1, 5),
                            $this->loc(Symbol::create('__short_fn_1_1'), 1, 6, 1, 8),
                            $this->loc(Symbol::create('__short_fn_3_2'), 1, 9, 1, 11),
                        ]),
                        1,
                        0,
                        1,
                        12,
                    ),
                ]),
                1,
                0,
                1,
                12,
            ),
            $this->read('#(add %1 %3)'),
        );
    }

    public function test_hash_fn_rest_argument_multiple_times(): void
    {
        self::assertEquals(
            $this->loc(
                Phel::list([
                    Symbol::create(Symbol::NAME_FN),
                    Phel::vector([
                        Symbol::create('&'),
                        Symbol::create('__short_fn_rest_1'),
                    ]),
                    $this->loc(
                        Phel::list([
                            $this->loc(Symbol::create('concat'), 1, 2, 1, 8),
                            $this->loc(Symbol::create('__short_fn_rest_1'), 1, 9, 1, 11),
                            $this->loc(Symbol::create('__short_fn_rest_1'), 1, 12, 1, 14),
                        ]),
                        1,
                        0,
                        1,
                        15,
                    ),
                ]),
                1,
                0,
                1,
                15,
            ),
            $this->read('#(concat %& %&)'),
        );
    }

    public function test_dollar_is_regular_symbol_inside_hash_fn(): void
    {
        self::assertEquals(
            $this->loc(
                Phel::list([
                    Symbol::create(Symbol::NAME_FN),
                    Phel::vector(),
                    $this->loc(
                        Phel::list([
                            $this->loc(Symbol::create('add'), 1, 2, 1, 5),
                            $this->loc(Symbol::create('$'), 1, 6, 1, 7),
                        ]),
                        1,
                        0,
                        1,
                        8,
                    ),
                ]),
                1,
                0,
                1,
                8,
            ),
            $this->read('#(add $)'),
        );
    }

    public function test_percent_is_regular_symbol_inside_pipe_fn(): void
    {
        self::assertEquals(
            $this->loc(
                Phel::list([
                    Symbol::create(Symbol::NAME_FN),
                    Phel::vector(),
                    $this->loc(
                        Phel::list([
                            $this->loc(Symbol::create('add'), 1, 2, 1, 5),
                            $this->loc(Symbol::create('%'), 1, 6, 1, 7),
                        ]),
                        1,
                        0,
                        1,
                        8,
                    ),
                ]),
                1,
                0,
                1,
                8,
            ),
            $this->read('|(add %)'),
        );
    }

    public function test_percent_is_regular_symbol_outside_short_fn(): void
    {
        self::assertEquals(
            $this->loc(
                Phel::list([
                    $this->loc(Symbol::create('add'), 1, 1, 1, 4),
                    $this->loc(Symbol::create('%'), 1, 5, 1, 6),
                ]),
                1,
                0,
                1,
                7,
            ),
            $this->read('(add %)'),
        );
    }

    public function test_pipe_fn_then_hash_fn_prefix_isolation(): void
    {
        $pipeResult = $this->read('|(add $)');
        $hashResult = $this->read('#(add %)');

        self::assertEquals(
            $this->loc(
                Phel::list([
                    Symbol::create(Symbol::NAME_FN),
                    Phel::vector([
                        Symbol::create('__short_fn_1_1'),
                    ]),
                    $this->loc(
                        Phel::list([
                            $this->loc(Symbol::create('add'), 1, 2, 1, 5),
                            $this->loc(Symbol::create('__short_fn_1_1'), 1, 6, 1, 7),
                        ]),
                        1,
                        0,
                        1,
                        8,
                    ),
                ]),
                1,
                0,
                1,
                8,
            ),
            $pipeResult,
        );

        self::assertEquals(
            $this->loc(
                Phel::list([
                    Symbol::create(Symbol::NAME_FN),
                    Phel::vector([
                        Symbol::create('__short_fn_1_1'),
                    ]),
                    $this->loc(
                        Phel::list([
                            $this->loc(Symbol::create('add'), 1, 2, 1, 5),
                            $this->loc(Symbol::create('__short_fn_1_1'), 1, 6, 1, 7),
                        ]),
                        1,
                        0,
                        1,
                        8,
                    ),
                ]),
                1,
                0,
                1,
                8,
            ),
            $hashResult,
        );
    }

    public function test_regex_literal(): void
    {
        self::assertSame(
            '/\d+/',
            $this->read('#"\d+"'),
        );
    }

    public function test_uuid_tagged_literal_reads_as_string(): void
    {
        self::assertSame(
            '00000000-0000-0000-0000-000000000000',
            $this->read('#uuid "00000000-0000-0000-0000-000000000000"'),
        );
    }

    public function test_uuid_tagged_literal_lowercases_input(): void
    {
        self::assertSame(
            '550e8400-e29b-41d4-a716-446655440000',
            $this->read('#uuid "550E8400-E29B-41D4-A716-446655440000"'),
        );
    }

    public function test_uuid_tagged_literal_rejects_invalid_format(): void
    {
        $this->expectException(ReaderException::class);
        $this->expectExceptionMessage('is not a canonical UUID string');
        $this->read('#uuid "not-a-uuid"');
    }

    public function test_uuid_tagged_literal_requires_string_form(): void
    {
        $this->expectException(ReaderException::class);
        $this->expectExceptionMessage('#uuid expects a string literal');
        $this->read('#uuid 42');
    }

    public function test_unknown_tagged_literal_throws(): void
    {
        $this->expectException(ReaderException::class);
        $this->expectExceptionMessage("Unknown tagged literal '#something'");
        $this->read('#something "x"');
    }

    public function test_php_tagged_literal_on_vector_expands_to_indexed_array_call(): void
    {
        $form = $this->read('#php [1 2 3]');

        self::assertInstanceOf(PersistentListInterface::class, $form);
        self::assertSame('php-indexed-array', $form->get(0)->getName());
        self::assertSame(1, $form->get(1));
        self::assertSame(2, $form->get(2));
        self::assertSame(3, $form->get(3));
    }

    public function test_php_tagged_literal_on_map_expands_to_associative_array_call(): void
    {
        $form = $this->read('#php {"a" 1 "b" 2}');

        self::assertInstanceOf(PersistentListInterface::class, $form);
        self::assertSame('php-associative-array', $form->get(0)->getName());
    }

    public function test_php_tagged_literal_rejects_non_collection_form(): void
    {
        $this->expectException(ReaderException::class);
        $this->expectExceptionMessage('#php expects a vector literal');
        $this->read('#php 42');
    }

    public function test_inst_tagged_literal_returns_datetime(): void
    {
        BuiltinTagHandlers::registerAll(TagRegistry::getInstance());

        $result = $this->readAny('#inst "2026-04-20T12:00:00Z"');

        self::assertInstanceOf(DateTimeImmutable::class, $result);
        self::assertSame('2026-04-20T12:00:00+00:00', $result->format(DATE_ATOM));
    }

    public function test_inst_tagged_literal_defaults_missing_offset_to_utc(): void
    {
        BuiltinTagHandlers::registerAll(TagRegistry::getInstance());

        $result = $this->readAny('#inst "2026-04-20T12:00:00"');

        self::assertInstanceOf(DateTimeImmutable::class, $result);
        self::assertSame('2026-04-20T12:00:00+00:00', $result->format(DATE_ATOM));
    }

    public function test_inst_tagged_literal_rejects_invalid_string(): void
    {
        BuiltinTagHandlers::registerAll(TagRegistry::getInstance());

        $this->expectException(ReaderException::class);
        $this->expectExceptionMessage('is not a valid ISO 8601');
        $this->read('#inst "bad-date"');
    }

    public function test_regex_tagged_literal_returns_delimited_pattern(): void
    {
        BuiltinTagHandlers::registerAll(TagRegistry::getInstance());

        self::assertSame('/[a-z]+/', $this->read('#regex "[a-z]+"'));
    }

    public function test_regex_tagged_literal_rejects_non_string(): void
    {
        BuiltinTagHandlers::registerAll(TagRegistry::getInstance());

        $this->expectException(ReaderException::class);
        $this->expectExceptionMessage('#regex expects a string literal');
        $this->read('#regex 42');
    }

    public function test_unknown_tag_lists_registered_tags(): void
    {
        $registry = TagRegistry::getInstance();
        $registry->clear();
        BuiltinTagHandlers::registerAll($registry);

        $this->expectException(ReaderException::class);
        $this->expectExceptionMessage('Registered tags: #inst, #php, #regex, #uuid');
        $this->read('#xyz "x"');
    }

    public function test_runtime_registered_tag_applies_handler(): void
    {
        $registry = TagRegistry::getInstance();
        BuiltinTagHandlers::registerAll($registry);
        $registry->register('shout', static fn(mixed $s): string => strtoupper((string) $s));

        try {
            self::assertSame('HELLO', $this->read('#shout "hello"'));
        } finally {
            $registry->unregister('shout');
        }
    }

    public function test_handler_throwing_arbitrary_error_is_wrapped_with_tag_name(): void
    {
        $registry = TagRegistry::getInstance();
        BuiltinTagHandlers::registerAll($registry);
        $registry->register('boom', static function (mixed $_): never {
            throw new RuntimeException('kaboom');
        });

        try {
            $this->expectException(ReaderException::class);
            $this->expectExceptionMessage("Tagged-literal handler for '#boom' threw an error: kaboom");
            $this->read('#boom "ignored"');
        } finally {
            $registry->unregister('boom');
        }
    }

    public function test_unknown_tag_error_points_to_register_tag_api(): void
    {
        $registry = TagRegistry::getInstance();
        $registry->clear();
        BuiltinTagHandlers::registerAll($registry);

        $this->expectException(ReaderException::class);
        $this->expectExceptionMessage('Use `(register-tag "xyz" f)` to register a handler');
        $this->read('#xyz "x"');
    }

    private function read(string $string, bool $withLocation = true): float|bool|int|string|TypeInterface|null
    {
        $ast = $this->readAny($string, $withLocation);

        if ($ast === null || is_scalar($ast) || $ast instanceof TypeInterface) {
            return $ast;
        }

        self::fail(sprintf('Unexpected read result of type %s', get_debug_type($ast)));
    }

    private function readAny(string $string, bool $withLocation = true): mixed
    {
        Symbol::resetGen();
        $tokenStream = $this->compilerFacade->lexString($string, Lexer::DEFAULT_SOURCE, $withLocation);

        do {
            $parseTree = $this->compilerFacade->parseNext($tokenStream);
            if (!$parseTree instanceof NodeInterface) {
                return null;
            }
        } while ($parseTree instanceof TriviaNodeInterface);

        return $this->compilerFacade->read($parseTree)->getAst();
    }

    private function withMeta(mixed $x, PersistentMapInterface $t): mixed
    {
        return $x->withMeta($t);
    }

    private function loc(TypeInterface $x, int $beginLine, int $beginColumn, int $endLine, int $endColumn): TypeInterface
    {
        return $x
            ->setStartLocation(new SourceLocation('string', $beginLine, $beginColumn))
            ->setEndLocation(new SourceLocation('string', $endLine, $endColumn));
    }
}
