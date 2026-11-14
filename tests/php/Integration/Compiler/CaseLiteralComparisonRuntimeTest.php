<?php

declare(strict_types=1);

namespace PhelTest\Integration\Compiler;

use Phel;
use Phel\Lang\BigInt;
use Phel\Lang\EqualsInterface;
use Phel\Lang\Keyword;
use Phel\Lang\Ratio;
use Phel\Lang\Symbol;
use Phel\Shared\CompileOptions;
use PHPUnit\Framework\Attributes\DataProvider;

final class CaseLiteralComparisonRuntimeTest extends AbstractCompilerRuntimeTestCase
{
    public function test_statement_arms_use_identity_and_keyword_slots(): void
    {
        $php = $this->compilerFacade->compile(
            '(fn [shape] (case (:type shape) :circle (* 3.14 (:r shape) (:r shape)) :rect (* (:w shape) (:h shape)) 0))',
            new CompileOptions()->setEmitOnly(true)->setOptimizationLevel(2),
        )->getPhpCode();

        self::assertStringContainsString(' === ', $php);
        self::assertStringNotContainsString('"equals1"', $php);
        self::assertSame(
            preg_match_all('/Keyword::create\(/', $php),
            preg_match_all('/\?\?= \\\\Phel\\\\Lang\\\\Keyword::create\(/', $php),
        );
    }

    #[DataProvider('literalSubjects')]
    public function test_statement_case_agrees_with_runtime_equality(string $key, string $literal, mixed $subject): void
    {
        $optimized = $this->compilerFacade->eval(
            '(fn [x] (case x ' . $key . ' (let [v 3] (php/+ v 1)) :unmatched -1 0))',
        );
        $runtime = $this->compilerFacade->eval(
            '(fn [x] (let [eq =] (if (eq x ' . $literal . ') 4 (if (eq x :unmatched) -1 0))))',
        );

        self::assertSame($runtime($subject), $optimized($subject));
    }

    #[DataProvider('literalSubjects')]
    public function test_literal_case_agrees_with_runtime_equality(string $key, string $literal, mixed $subject): void
    {
        $optimized = $this->compilerFacade->eval('(fn [x] (case x ' . $key . ' 4 :unmatched -1 0))');
        $runtime = $this->compilerFacade->eval(
            '(fn [x] (let [eq =] (if (eq x ' . $literal . ') 4 (if (eq x :unmatched) -1 0))))',
        );

        self::assertSame($runtime($subject), $optimized($subject));
    }

    public function test_case_without_default_evaluates_its_subject_once_and_returns_nil(): void
    {
        $fn = $this->compilerFacade->eval(
            '(fn [next-value] (case (next-value) :a (let [v 3] (php/+ v 1)) :b (let [v 5] (php/+ v 1))))',
        );
        $calls = 0;
        $nextValue = static function () use (&$calls): mixed {
            ++$calls;
            return null;
        };

        self::assertNull($fn($nextValue));
        self::assertSame(1, $calls);
    }

    public function test_literal_on_the_left_keeps_its_equality_precedence(): void
    {
        $subject = self::keywordEqualitySubject();
        $optimized = $this->compilerFacade->eval('(fn [x] (if (= :foo x) (let [v 3] (php/+ v 1)) 0))');
        $runtime = $this->compilerFacade->eval('(fn [x] (let [eq =] (if (eq :foo x) 4 0)))');

        self::assertSame($runtime($subject), $optimized($subject));
    }

    public function test_literal_match_fallback_keeps_equality_order_and_evaluates_subject_once(): void
    {
        $subject = self::keywordEqualitySubject();
        $optimized = $this->compilerFacade->eval('(fn [next-value] (case (next-value) :foo 4 :bar 5 0))');
        $calls = 0;
        $nextValue = static function () use (&$calls, $subject): EqualsInterface {
            ++$calls;
            return $subject;
        };

        self::assertSame(4, $optimized($nextValue));
        self::assertSame(1, $calls);

        $literalFirst = $this->compilerFacade->eval('(fn [x] (cond (= :foo x) 4 (= :bar x) 5 :else 0))');
        self::assertSame(0, $literalFirst($subject));
    }

    public function test_cond_rereads_a_reference_changed_by_a_previous_equality_test(): void
    {
        $fn = $this->compilerFacade->eval('(fn [^:by-ref x] (cond (= x :a) 1 (= x :b) 2 :else 0))');
        $subject = null;
        $subject = new class($subject) implements EqualsInterface {
            public function __construct(private mixed &$subject) {}

            public function equals(mixed $other): bool
            {
                $this->subject = Keyword::create('b');
                return false;
            }
        };

        self::assertSame(2, $fn($subject));
        self::assertSame(Keyword::create('b'), $subject);
    }

    public function test_redefined_case_equality_keeps_runtime_dispatch(): void
    {
        $original = Phel::getDefinition('phel.core', 'equals1');
        $meta = Phel::getDefinitionMetaData('phel.core', 'equals1');
        self::assertNotNull($meta);

        try {
            Phel::addDefinition('phel.core', 'equals1', static fn(mixed $a, mixed $b): bool => false, $meta->put(Keyword::create('redef'), true));
            $fn = $this->compilerFacade->eval('(fn [x] (case x :a 1 :b 2 0))');

            self::assertSame(0, $fn(Keyword::create('a')));
        } finally {
            Phel::addDefinition('phel.core', 'equals1', $original, $meta);
        }
    }

    /**
     * @return iterable<string, array{string, string, mixed}>
     */
    public static function literalSubjects(): iterable
    {
        yield 'integer' => ['42', '42', 42];
        yield 'integer and float differ' => ['42', '42', 42.0];
        yield 'integer and bigint agree' => ['42', '42', BigInt::fromInt(42)];
        yield 'integer and fractional ratio differ' => ['42', '42', Ratio::create(85, 2)];
        yield 'string' => ['"foo"', '"foo"', 'foo'];
        yield 'string and integer differ' => ['"42"', '"42"', 42];
        yield 'true' => ['true', 'true', true];
        yield 'false' => ['false', 'false', false];
        yield 'boolean and integer differ' => ['true', 'true', 1];
        yield 'nil' => ['nil', 'nil', null];
        yield 'nil subject' => [':foo', ':foo', null];
        yield 'keyword' => [':foo', ':foo', Keyword::create('foo')];
        yield 'noninterned keyword' => [':foo', ':foo', unserialize(serialize(Keyword::create('foo')))];
        yield 'other keyword' => [':foo', ':foo', Keyword::create('bar')];
        yield 'keyword with namespace' => [':example/foo', ':example/foo', Keyword::create('foo', 'example')];
        yield 'float' => ['1.5', '1.5', 1.5];
        yield 'ratio' => ['1/2', '1/2', Ratio::create(1, 2)];
        yield 'bigint' => ['9223372036854775808', '9223372036854775808', BigInt::fromString('9223372036854775808')];
        yield 'symbol' => ['foo', "'foo", Symbol::create('foo')];
        yield 'vector' => ['[1 2]', '[1 2]', Phel::vector([1, 2])];
        yield 'map' => ['{:a 1}', '{:a 1}', Phel::map(Keyword::create('a'), 1)];
        yield 'set' => ['#{1 2}', '#{1 2}', Phel::set([1, 2])];
        yield 'custom equality' => [':foo', ':foo', self::keywordEqualitySubject()];
    }

    private static function keywordEqualitySubject(): EqualsInterface
    {
        return new class() implements EqualsInterface {
            public function equals(mixed $other): bool
            {
                return $other instanceof Keyword && $other->getName() === 'foo';
            }
        };
    }
}
