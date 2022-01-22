<?php

declare(strict_types=1);

namespace PhelTest\Integration\Compiler\Reader;

use Phel\Compiler\Analyzer\Environment\GlobalEnvironmentSingleton;
use Phel\Compiler\CompilerFacade;
use Phel\Compiler\CompilerFacadeInterface;
use Phel\Compiler\Lexer\Lexer;
use Phel\Compiler\Reader\Exceptions\ReaderException;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Keyword;
use Phel\Lang\Registry;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;
use Phel\Lang\TypeFactory;
use Phel\Lang\TypeInterface;
use PHPUnit\Framework\TestCase;

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
        Registry::getInstance()->clear();
    }

    public function setUp(): void
    {
        Symbol::resetGen();
        $this->compilerFacade = new CompilerFacade();
    }

    public function test_read_number(): void
    {
        self::assertEquals(1, $this->read('1'));
        self::assertEquals(10, $this->read('10'));
        self::assertEquals(100, $this->read('100'));
        self::assertEquals(10.0, $this->read('10.0'));
        self::assertEquals(1.1, $this->read('1.1'));
        self::assertEquals(10.11, $this->read('10.11'));
        self::assertEquals(1337, $this->read('0x539'));
        self::assertEquals(1337, $this->read('0x5_3_9'));
        self::assertEquals(1337, $this->read('02471'));
        self::assertEquals(1337, $this->read('024_71'));
        self::assertEquals(1337, $this->read('0b10100111001'));
        self::assertEquals(1337, $this->read('0b0101_0011_1001'));
        self::assertEquals(1337, $this->read('1337e0'));
        self::assertEquals(-1337, $this->read('-1337'));
        self::assertEquals(-1337.0, $this->read('-1337.0'));
        self::assertEquals(1337, $this->read('+1337'));
        self::assertEquals(1337, $this->read('+1337.0'));
        self::assertEquals(1.2e3, $this->read('1.2e3'));
        self::assertEquals(7E-10, $this->read('7E-10'));
    }

    public function test_read_keyword(): void
    {
        self::assertEquals(
            $this->loc(Keyword::create('test'), 1, 0, 1, 5),
            $this->read(':test')
        );
    }

    public function test_read_boolean(): void
    {
        self::assertEquals(true, $this->read('true'));
        self::assertEquals(false, $this->read('false'));
    }

    public function test_read_nil(): void
    {
        self::assertNull(
            $this->read('nil')
        );
    }

    public function test_read_symbol(): void
    {
        self::assertEquals(
            $this->loc(Symbol::create('test'), 1, 0, 1, 4),
            $this->read('test')
        );
    }

    public function test_read_list(): void
    {
        self::assertEquals(
            $this->loc(TypeFactory::getInstance()->emptyPersistentList(), 1, 0, 1, 2),
            $this->read('()')
        );
        self::assertEquals(
            $this->loc(TypeFactory::getInstance()->persistentListFromArray([
                $this->loc(TypeFactory::getInstance()->emptyPersistentList(), 1, 1, 1, 3),
            ]), 1, 0, 1, 4),
            $this->read('(())')
        );

        self::assertEquals(
            $this->loc(TypeFactory::getInstance()->persistentListFromArray([
                $this->loc(Symbol::create('a'), 1, 1, 1, 2),
            ]), 1, 0, 1, 3),
            $this->read('(a)')
        );

        self::assertEquals(
            $this->loc(TypeFactory::getInstance()->persistentListFromArray([
                $this->loc(Symbol::create('a'), 1, 1, 1, 2),
                $this->loc(Symbol::create('b'), 1, 3, 1, 4),
            ]), 1, 0, 1, 5),
            $this->read('(a b)')
        );
    }

    public function test_read_vector(): void
    {
        self::assertEquals(
            $this->loc(TypeFactory::getInstance()->emptyPersistentVector(), 1, 0, 1, 2),
            $this->read('[]')
        );
        self::assertEquals(
            $this->loc(TypeFactory::getInstance()->persistentVectorFromArray([
                $this->loc(TypeFactory::getInstance()->emptyPersistentVector(), 1, 1, 1, 3),
            ]), 1, 0, 1, 4),
            $this->read('[[]]')
        );

        self::assertEquals(
            $this->loc(TypeFactory::getInstance()->persistentVectorFromArray([
                $this->loc(Symbol::create('a'), 1, 1, 1, 2),
            ]), 1, 0, 1, 3),
            $this->read('[a]')
        );

        self::assertEquals(
            $this->loc(TypeFactory::getInstance()->persistentVectorFromArray([
                $this->loc(Symbol::create('a'), 1, 1, 1, 2),
                $this->loc(Symbol::create('b'), 1, 3, 1, 4),
            ]), 1, 0, 1, 5),
            $this->read('[a b]')
        );
    }

    public function test_quote(): void
    {
        self::assertEquals(
            $this->loc(TypeFactory::getInstance()->persistentListFromArray([
                Symbol::create(Symbol::NAME_QUOTE),
                $this->loc(Symbol::create('a'), 1, 1, 1, 2),
            ]), 1, 0, 1, 2),
            $this->read('\'a')
        );
    }

    public function test_unquote(): void
    {
        self::assertEquals(
            $this->loc(TypeFactory::getInstance()->persistentListFromArray([
                Symbol::create(Symbol::NAME_UNQUOTE),
                $this->loc(Symbol::create('a'), 1, 1, 1, 2),
            ]), 1, 0, 1, 2),
            $this->read(',a')
        );
    }

    public function test_unquote_splice(): void
    {
        self::assertEquals(
            $this->loc(TypeFactory::getInstance()->persistentListFromArray([
                Symbol::create(Symbol::NAME_UNQUOTE_SPLICING),
                $this->loc(Symbol::create('a'), 1, 2, 1, 3),
            ]), 1, 0, 1, 3),
            $this->read(',@a')
        );
    }

    public function test_quasiquote1(): void
    {
        self::assertEquals(
            $this->loc(TypeFactory::getInstance()->persistentListFromArray([
                $this->loc(Symbol::create(Symbol::NAME_QUOTE), 1, 1, 1, 8),
                $this->loc(Symbol::create(Symbol::NAME_UNQUOTE), 1, 1, 1, 8),
            ]), 1, 0, 1, 8),
            $this->read(sprintf('`%s', Symbol::NAME_UNQUOTE))
        );
    }

    public function test_quasiquote2(): void
    {
        self::assertEquals(
            $this->loc(TypeFactory::getInstance()->persistentListFromArray([
                $this->loc(Symbol::create(Symbol::NAME_QUOTE), 1, 1, 1, 2),
                $this->loc(Symbol::create('a'), 1, 1, 1, 2),
            ]), 1, 0, 1, 2),
            $this->read('`a')
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
        $l1 = $this->read('\'a', true);
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

    public function test_read_string(): void
    {
        self::assertEquals(
            'abc',
            $this->read('"abc"')
        );

        self::assertEquals(
            'ab"c',
            $this->read('"ab\"c"')
        );

        self::assertEquals(
            "\\\r\n\t\f\v\e\$",
            $this->read('"\\\\\r\n\t\f\v\e\$"')
        );

        self::assertEquals(
            'read $abc sign',
            $this->read('"read $abc sign"')
        );

        self::assertEquals(
            "\x41",
            $this->read('"\x41"')
        );

        self::assertEquals(
            "\u{65}",
            $this->read('"\u{65}"')
        );

        self::assertEquals(
            "\u{129}",
            $this->read('"\u{129}"')
        );

        self::assertEquals(
            "\u{1000}",
            $this->read('"\u{1000}"')
        );

        self::assertEquals(
            "\u{10000}",
            $this->read('"\u{10000}"')
        );

        self::assertEquals(
            "\77",
            $this->read('"\77"')
        );
    }

    public function test_read_empty_map(): void
    {
        self::assertEquals(
            $this->loc(TypeFactory::getInstance()->emptyPersistentMap(), 1, 0, 1, 2),
            $this->read('{}')
        );
    }

    public function test_map_table1(): void
    {
        self::assertEquals(
            $this->loc(
                TypeFactory::getInstance()->persistentMapFromKVs(
                    $this->loc(Keyword::create('a'), 1, 1, 1, 3),
                    1
                ),
                1,
                0,
                1,
                6
            ),
            $this->read('{:a 1}')
        );
    }

    public function test_map_table2(): void
    {
        self::assertEquals(
            $this->loc(TypeFactory::getInstance()->persistentMapFromKVs(
                $this->loc(Keyword::create('a'), 1, 1, 1, 3),
                1,
                $this->loc(Keyword::create('b'), 1, 6, 1, 8),
                2
            ), 1, 0, 1, 11),
            $this->read('{:a 1 :b 2}')
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
                    TypeFactory::getInstance()->persistentMapFromKVs(
                        $this->loc(Keyword::create('test'), 1, 1, 1, 6),
                        true
                    )
                ),
                1,
                7,
                1,
                11
            ),
            $this->read('^:test test')
        );
    }

    public function test_meta_string(): void
    {
        self::assertEquals(
            $this->loc(
                $this->withMeta(
                    Symbol::create('test'),
                    TypeFactory::getInstance()->persistentMapFromKVs(
                        Keyword::create('tag'),
                        'test'
                    )
                ),
                1,
                8,
                1,
                12
            ),
            $this->read('^"test" test')
        );
    }

    public function test_meta_symbol(): void
    {
        self::assertEquals(
            $this->loc(
                $this->withMeta(
                    Symbol::create('test'),
                    TypeFactory::getInstance()->persistentMapFromKVs(
                        Keyword::create('tag'),
                        $this->loc(Symbol::create('String'), 1, 1, 1, 7)
                    )
                ),
                1,
                8,
                1,
                12
            ),
            $this->read('^String test')
        );
    }

    public function test_meta_table(): void
    {
        self::assertEquals(
            $this->loc(
                $this->withMeta(
                    Symbol::create('test'),
                    TypeFactory::getInstance()->persistentMapFromKVs(
                        $this->loc(Keyword::create('a'), 1, 2, 1, 4),
                        1,
                        $this->loc(Keyword::create('b'), 1, 7, 1, 9),
                        2
                    )
                ),
                1,
                13,
                1,
                17
            ),
            $this->read('^{:a 1 :b 2} test')
        );
    }

    public function test_concat_meta(): void
    {
        self::assertEquals(
            $this->loc(
                $this->withMeta(
                    Symbol::create('test'),
                    TypeFactory::getInstance()->persistentMapFromKVs(
                        $this->loc(Keyword::create('b'), 1, 5, 1, 7),
                        true,
                        $this->loc(Keyword::create('a'), 1, 1, 1, 3),
                        true
                    )
                ),
                1,
                8,
                1,
                12
            ),
            $this->read('^:a ^:b test')
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
                TypeFactory::getInstance()->persistentListFromArray([
                    Symbol::create(Symbol::NAME_FN),
                    TypeFactory::getInstance()->emptyPersistentVector(),
                    $this->loc(
                        TypeFactory::getInstance()->persistentListFromArray([
                            $this->loc(Symbol::create('add'), 1, 2, 1, 5),
                        ]),
                        1,
                        0,
                        1,
                        6
                    ),
                ]),
                1,
                0,
                1,
                6
            ),
            $this->read('|(add)')
        );
    }

    public function test_read_short_fn_one_arg(): void
    {
        self::assertEquals(
            $this->loc(
                TypeFactory::getInstance()->persistentListFromArray([
                    Symbol::create(Symbol::NAME_FN),
                    TypeFactory::getInstance()->persistentVectorFromArray([
                        Symbol::create('__short_fn_1_1'),
                    ]),
                    $this->loc(
                        TypeFactory::getInstance()->persistentListFromArray([
                            $this->loc(Symbol::create('add'), 1, 2, 1, 5),
                            $this->loc(Symbol::create('__short_fn_1_1'), 1, 6, 1, 7),
                        ]),
                        1,
                        0,
                        1,
                        8
                    ),
                ]),
                1,
                0,
                1,
                8
            ),
            $this->read('|(add $)')
        );
    }

    public function test_read_short_fn_one_arg_two_times(): void
    {
        self::assertEquals(
            $this->loc(
                TypeFactory::getInstance()->persistentListFromArray([
                    Symbol::create(Symbol::NAME_FN),
                    TypeFactory::getInstance()->persistentVectorFromArray([
                        Symbol::create('__short_fn_1_1'),
                    ]),
                    $this->loc(
                        TypeFactory::getInstance()->persistentListFromArray([
                            $this->loc(Symbol::create('add'), 1, 2, 1, 5),
                            $this->loc(Symbol::create('__short_fn_1_1'), 1, 6, 1, 7),
                            $this->loc(Symbol::create('__short_fn_1_1'), 1, 8, 1, 9),
                        ]),
                        1,
                        0,
                        1,
                        10
                    ),
                ]),
                1,
                0,
                1,
                10
            ),
            $this->read('|(add $ $)')
        );
    }

    public function test_read_short_fn_two_arguments(): void
    {
        self::assertEquals(
            $this->loc(
                TypeFactory::getInstance()->persistentListFromArray([
                    Symbol::create(Symbol::NAME_FN),
                    TypeFactory::getInstance()->persistentVectorFromArray([
                        Symbol::create('__short_fn_1_1'),
                        Symbol::create('__short_fn_2_2'),
                    ]),
                    $this->loc(
                        TypeFactory::getInstance()->persistentListFromArray([
                            $this->loc(Symbol::create('add'), 1, 2, 1, 5),
                            $this->loc(Symbol::create('__short_fn_1_1'), 1, 6, 1, 8),
                            $this->loc(Symbol::create('__short_fn_2_2'), 1, 9, 1, 11),
                        ]),
                        1,
                        0,
                        1,
                        12
                    ),
                ]),
                1,
                0,
                1,
                12
            ),
            $this->read('|(add $1 $2)')
        );
    }

    public function test_read_short_fn_arguments_twice(): void
    {
        self::assertEquals(
            $this->loc(
                TypeFactory::getInstance()->persistentListFromArray([
                    Symbol::create(Symbol::NAME_FN),
                    TypeFactory::getInstance()->persistentVectorFromArray([
                        Symbol::create('__short_fn_1_1'),
                    ]),
                    $this->loc(
                        TypeFactory::getInstance()->persistentListFromArray([
                            $this->loc(Symbol::create('add'), 1, 2, 1, 5),
                            $this->loc(Symbol::create('__short_fn_1_1'), 1, 6, 1, 8),
                            $this->loc(Symbol::create('__short_fn_1_1'), 1, 9, 1, 11),
                        ]),
                        1,
                        0,
                        1,
                        12
                    ),
                ]),
                1,
                0,
                1,
                12
            ),
            $this->read('|(add $1 $1)')
        );
    }

    public function test_read_short_fn_missing_argument(): void
    {
        self::assertEquals(
            $this->loc(
                TypeFactory::getInstance()->persistentListFromArray([
                    Symbol::create(Symbol::NAME_FN),
                    TypeFactory::getInstance()->persistentVectorFromArray([
                        Symbol::create('__short_fn_1_1'),
                        Symbol::create('__short_fn_undefined_3'),
                        Symbol::create('__short_fn_3_2'),
                    ]),
                    $this->loc(
                        TypeFactory::getInstance()->persistentListFromArray([
                            $this->loc(Symbol::create('add'), 1, 2, 1, 5),
                            $this->loc(Symbol::create('__short_fn_1_1'), 1, 6, 1, 8),
                            $this->loc(Symbol::create('__short_fn_3_2'), 1, 9, 1, 11),
                        ]),
                        1,
                        0,
                        1,
                        12
                    ),
                ]),
                1,
                0,
                1,
                12
            ),
            $this->read('|(add $1 $3)')
        );
    }

    public function test_read_short_fn_rest_arguments(): void
    {
        self::assertEquals(
            $this->loc(
                TypeFactory::getInstance()->persistentListFromArray([
                    Symbol::create(Symbol::NAME_FN),
                    TypeFactory::getInstance()->persistentVectorFromArray([
                        Symbol::create('__short_fn_1_1'),
                        Symbol::create('&'),
                        Symbol::create('__short_fn_rest_2'),
                    ]),
                    $this->loc(
                        TypeFactory::getInstance()->persistentListFromArray([
                            $this->loc(Symbol::create('add'), 1, 2, 1, 5),
                            $this->loc(Symbol::create('__short_fn_1_1'), 1, 6, 1, 8),
                            $this->loc(Symbol::create('__short_fn_rest_2'), 1, 9, 1, 11),
                        ]),
                        1,
                        0,
                        1,
                        12
                    ),
                ]),
                1,
                0,
                1,
                12
            ),
            $this->read('|(add $1 $&)')
        );
    }

    public function test_short_fn_rest_argument_multiple_times(): void
    {
        $this->assertEquals(
            $this->loc(
                TypeFactory::getInstance()->persistentListFromArray([
                    Symbol::create(Symbol::NAME_FN),
                    TypeFactory::getInstance()->persistentVectorFromArray([
                        Symbol::create('&'),
                        Symbol::create('__short_fn_rest_1'),
                    ]),
                    $this->loc(
                        TypeFactory::getInstance()->persistentListFromArray([
                            $this->loc(Symbol::create('concat'), 1, 2, 1, 8),
                            $this->loc(Symbol::create('__short_fn_rest_1'), 1, 9, 1, 11),
                            $this->loc(Symbol::create('__short_fn_rest_1'), 1, 12, 1, 14),
                        ]),
                        1,
                        0,
                        1,
                        15
                    ),
                ]),
                1,
                0,
                1,
                15
            ),
            $this->read('|(concat $& $&)')
        );
    }

    /**
     * @return TypeInterface|string|float|int|bool|null
     */
    private function read(string $string, bool $withLocation = true)
    {
        Symbol::resetGen();
        $tokenStream = $this->compilerFacade->lexString($string, Lexer::DEFAULT_SOURCE, $withLocation);
        $parseTree = $this->compilerFacade->parseNext($tokenStream);

        return $this->compilerFacade->read($parseTree)->getAst();
    }

    /**
     * @param mixed $x
     *
     * @return mixed
     */
    private function withMeta($x, PersistentMapInterface $t)
    {
        return $x->withMeta($t);
    }

    private function loc(TypeInterface $x, $beginLine, $beginColumn, $endLine, $endColumn): TypeInterface
    {
        return $x
            ->setStartLocation(new SourceLocation('string', $beginLine, $beginColumn))
            ->setEndLocation(new SourceLocation('string', $endLine, $endColumn));
    }
}
