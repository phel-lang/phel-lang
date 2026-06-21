<?php

declare(strict_types=1);

namespace PhelTest\Integration\Compiler;

use Phel;
use Phel\Build\BuildFacade;
use Phel\Compiler\CompilerFacade;
use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironmentInterface;
use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;
use Phel\Shared\CompileOptions;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end check that `(= x :kw)` lowered to native `===` (and the
 * `(not (= x :kw))` peephole lowered to `!==`) is behaviour-preserving
 * against the runtime `phel.core/=` dispatch for every shape of the other
 * operand: equal/unequal keyword, int, string, nil, and a different
 * keyword. Keywords are interned singletons so identity is exact equality.
 */
final class KeywordEqualityRuntimeTest extends TestCase
{
    private static GlobalEnvironmentInterface $globalEnv;

    private CompilerFacade $compilerFacade;

    public static function setUpBeforeClass(): void
    {
        Phel::bootstrap(__DIR__);
        Symbol::resetGen();
        $globalEnv = GlobalEnvironmentSingleton::initializeNew();
        new BuildFacade()->compileFile(
            __DIR__ . '/../../../../src/phel/core.phel',
            tempnam(sys_get_temp_dir(), 'phel-core'),
        );
        self::$globalEnv = $globalEnv;
    }

    protected function setUp(): void
    {
        $this->compilerFacade = new CompilerFacade();
        self::$globalEnv->setNs('user');
        Symbol::resetGen();
    }

    public function test_keyword_equality_keyword_matches(): void
    {
        /** @var callable $fn */
        $fn = $this->compilerFacade->eval('(fn [x] (= x :foo))', new CompileOptions());

        self::assertTrue($fn(Keyword::create('foo')), 'equal keyword is true');
    }

    public function test_keyword_equality_unequal_keyword(): void
    {
        /** @var callable $fn */
        $fn = $this->compilerFacade->eval('(fn [x] (= x :foo))', new CompileOptions());

        self::assertFalse($fn(Keyword::create('bar')), 'different keyword is false');
    }

    public function test_keyword_equality_against_non_keyword_is_false(): void
    {
        /** @var callable $fn */
        $fn = $this->compilerFacade->eval('(fn [x] (= x :foo))', new CompileOptions());

        self::assertFalse($fn(1), 'int vs keyword is false');
        self::assertFalse($fn('foo'), 'string vs keyword is false');
        self::assertFalse($fn(null), 'nil vs keyword is false');
    }

    public function test_keyword_literal_on_the_left(): void
    {
        /** @var callable $fn */
        $fn = $this->compilerFacade->eval('(fn [x] (= :foo x))', new CompileOptions());

        self::assertTrue($fn(Keyword::create('foo')), 'literal-on-left equal keyword is true');
        self::assertFalse($fn(Keyword::create('bar')), 'literal-on-left different keyword is false');
        self::assertFalse($fn('foo'), 'literal-on-left vs string is false');
    }

    public function test_negated_keyword_equality_peephole(): void
    {
        /** @var callable $fn */
        $fn = $this->compilerFacade->eval('(fn [x] (not (= x :foo)))', new CompileOptions());

        self::assertFalse($fn(Keyword::create('foo')), 'not= equal keyword is false');
        self::assertTrue($fn(Keyword::create('bar')), 'not= different keyword is true');
        self::assertTrue($fn(1), 'not= int is true');
    }

    /**
     * The optimized `===` lowering must produce the exact same boolean as
     * the runtime `phel.core/=` dispatch for every operand shape. The
     * runtime baseline is forced by aliasing `=` to a local so the
     * specializer's literal-keyword detector does not fire on it.
     */
    public function test_optimized_matches_runtime_equality(): void
    {
        /** @var callable $optimized */
        $optimized = $this->compilerFacade->eval('(fn [x] (= x :foo))', new CompileOptions());
        /** @var callable $runtime */
        $runtime = $this->compilerFacade->eval('(fn [x] (let [eq =] (eq x :foo)))', new CompileOptions());

        $cases = [
            Keyword::create('foo'),
            Keyword::create('bar'),
            Keyword::create('foo', 'ns'),
            1,
            0,
            'foo',
            '',
            null,
            true,
            false,
        ];

        foreach ($cases as $case) {
            self::assertSame(
                $runtime($case),
                $optimized($case),
                'optimized === must match runtime = for ' . var_export($case, true),
            );
        }
    }

    public function test_keyword_equality_in_cond_context(): void
    {
        /** @var callable $fn */
        $fn = $this->compilerFacade->eval(
            '(fn [x] (cond (= x :a) 1 (= x :b) 2 :else 3))',
            new CompileOptions(),
        );

        self::assertSame(1, $fn(Keyword::create('a')));
        self::assertSame(2, $fn(Keyword::create('b')));
        self::assertSame(3, $fn(Keyword::create('c')));
        self::assertSame(3, $fn('a'));
        self::assertSame(3, $fn(null));
    }

    public function test_keyword_equality_in_case_context(): void
    {
        /** @var callable $fn */
        $fn = $this->compilerFacade->eval(
            '(fn [x] (case x :a 1 :b 2 3))',
            new CompileOptions(),
        );

        self::assertSame(1, $fn(Keyword::create('a')));
        self::assertSame(2, $fn(Keyword::create('b')));
        self::assertSame(3, $fn(Keyword::create('c')));
    }
}
