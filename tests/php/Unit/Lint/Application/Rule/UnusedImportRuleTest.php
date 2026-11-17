<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lint\Application\Rule;

use Phel\Lint\Application\Rule\UnusedImportRule;
use Phel\Shared\LintRuleCodes;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

final class UnusedImportRuleTest extends RuleTestCase
{
    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_flags_unused_php_import(): void
    {
        $rule = new UnusedImportRule();
        $source = <<<PHEL
(ns user
  (:use DateTime))

42
PHEL;
        $analysis = $this->buildAnalysis($source);

        $diagnostics = $rule->apply($analysis);

        self::assertNotEmpty($diagnostics);
        self::assertSame(LintRuleCodes::UNUSED_IMPORT, $diagnostics[0]->code);
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_does_not_flag_used_import(): void
    {
        $rule = new UnusedImportRule();
        $source = <<<PHEL
(ns user
  (:use DateTime))

(php/new DateTime)
PHEL;
        $analysis = $this->buildAnalysis($source);

        self::assertSame([], $rule->apply($analysis));
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_does_not_flag_a_used_dot_separated_import(): void
    {
        $rule = new UnusedImportRule();
        // `(:use Phel.Lang.Keyword)` binds the alias `Keyword`, exactly as the
        // backslash spelling does.
        $source = <<<PHEL
(ns user
  (:use Phel.Lang.Keyword))

(php/:: Keyword (create "a"))
PHEL;
        $analysis = $this->buildAnalysis($source);

        self::assertSame([], $rule->apply($analysis));
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_flags_an_unused_dot_separated_import(): void
    {
        $rule = new UnusedImportRule();
        $source = <<<PHEL
(ns user
  (:use Phel.Lang.Keyword))

42
PHEL;
        $analysis = $this->buildAnalysis($source);

        $diagnostics = $rule->apply($analysis);

        self::assertCount(1, $diagnostics);
        self::assertStringContainsString('Phel.Lang.Keyword', $diagnostics[0]->message);
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_does_not_flag_a_used_backslash_separated_import(): void
    {
        $rule = new UnusedImportRule();
        $source = <<<PHEL
(ns user
  (:use Phel\\Lang\\Keyword))

(php/:: Keyword (create "a"))
PHEL;
        $analysis = $this->buildAnalysis($source);

        self::assertSame([], $rule->apply($analysis));
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_does_not_flag_import_used_only_in_a_loaded_file(): void
    {
        $source = <<<PHEL
(ns user
  (:use DateTimeImmutable))

(load "user/helpers")
PHEL;
        $loaded = <<<PHEL
(in-ns user)

(defn epoch [] (DateTimeImmutable/createFromFormat "U" "0"))
PHEL;

        self::assertSame([], new UnusedImportRule()->apply($this->buildAnalysisWithLoadedSource($source, $loaded)));
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_does_not_flag_when_a_load_cannot_be_followed(): void
    {
        $source = <<<PHEL
(ns user
  (:use DateTimeImmutable))

(load (str "user/" "helpers"))
PHEL;

        self::assertSame([], new UnusedImportRule()->apply($this->buildAnalysisWithLoadedSource($source, null)));
    }

    #[DataProvider('classReferences')]
    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_does_not_flag_an_import_referenced_through_a_class_form(string $body): void
    {
        $source = "(ns user\n  (:use DateTimeImmutable))\n\n" . $body;

        self::assertSame([], new UnusedImportRule()->apply($this->buildAnalysis($source)));
    }

    public static function classReferences(): iterable
    {
        yield 'constructor shorthand' => ['(DateTimeImmutable. "now")'];
        yield 'new' => ['(new DateTimeImmutable "now")'];
        yield 'static call' => ['(DateTimeImmutable/createFromFormat "U" "0")'];
        yield 'constant' => ['DateTimeImmutable/ATOM'];
        yield 'static property' => ['(set! DateTimeImmutable/$x 1)'];
        yield 'parameter tag' => ['(defn f [^DateTimeImmutable d] d)'];
        yield 'return tag' => ['(defn f ^DateTimeImmutable [] nil)'];
        yield 'let binding tag' => ['(let [^DateTimeImmutable d nil] d)'];
        yield 'tag map' => ['(def ^{:tag DateTimeImmutable} x nil)'];
        yield 'interface method tag' => ['(definterface I (^DateTimeImmutable m [this]))'];
        yield 'instance?' => ['(instance? DateTimeImmutable 1)'];
        yield 'catch' => ['(try 1 (catch DateTimeImmutable e 2))'];
        yield 'defstruct implements' => ['(defstruct p [x] DateTimeImmutable)'];
        yield 'nullable tag' => ['(defn f [^?DateTimeImmutable d] d)'];
        yield 'string tag' => ['(defn f [^"DateTimeImmutable" d] d)'];
        yield 'union tag' => ['(defn f [^"DateTimeImmutable|int" d] d)'];
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_flags_an_import_named_only_in_strings_and_other_tags(): void
    {
        $source = <<<PHEL
(ns user
  (:use DateTimeImmutable))

(defn ^:private f [^int x] (str x "DateTimeImmutable."))
PHEL;

        self::assertCount(1, new UnusedImportRule()->apply($this->buildAnalysis($source)));
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_a_constructor_of_another_class_with_the_same_short_name_is_not_a_use(): void
    {
        $source = <<<PHEL
(ns user
  (:use DateTimeImmutable))

(Other.DateTimeImmutable. "now")
PHEL;

        self::assertCount(1, new UnusedImportRule()->apply($this->buildAnalysis($source)));
    }

    #[DataProvider('nonClassReferences')]
    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_flags_an_import_named_only_outside_class_positions(string $body): void
    {
        $source = "(ns user\n  (:use DateTimeImmutable))\n\n" . $body;

        self::assertCount(1, new UnusedImportRule()->apply($this->buildAnalysis($source)));
    }

    public static function nonClassReferences(): iterable
    {
        yield 'other metadata' => ['(fn [^{:see-also [DateTimeImmutable]} x] x)'];
        yield 'dotted local' => ['(fn [DateTimeImmutable.] DateTimeImmutable.)'];
    }
}
