<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lint\Application\Rule;

use Phel\Lint\Application\Rule\UnusedRequireRule;
use Phel\Shared\LintRuleCodes;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

final class UnusedRequireRuleTest extends RuleTestCase
{
    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_flags_require_whose_alias_is_never_used(): void
    {
        $rule = new UnusedRequireRule();
        $source = <<<PHEL
(ns user
  (:require foo\\bar :as b))

42
PHEL;
        $analysis = $this->buildAnalysis($source);

        $diagnostics = $rule->apply($analysis);

        self::assertNotEmpty($diagnostics);
        self::assertSame(LintRuleCodes::UNUSED_REQUIRE, $diagnostics[0]->code);
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_does_not_flag_require_whose_alias_is_used(): void
    {
        $rule = new UnusedRequireRule();
        $source = <<<PHEL
(ns user
  (:require foo\\bar :as b))

(b/call 1)
PHEL;
        $analysis = $this->buildAnalysis($source);

        self::assertSame([], $rule->apply($analysis));
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_does_not_flag_a_dot_separated_require_used_via_its_implicit_alias(): void
    {
        $rule = new UnusedRequireRule();
        // `(:require foo.bar)` binds the alias `bar`, not `foo.bar`.
        $source = <<<PHEL
(ns user
  (:require foo.bar))

(bar/call 1)
PHEL;
        $analysis = $this->buildAnalysis($source);

        self::assertSame([], $rule->apply($analysis));
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_flags_a_dot_separated_require_that_is_never_used(): void
    {
        $rule = new UnusedRequireRule();
        $source = <<<PHEL
(ns user
  (:require foo.bar))

42
PHEL;
        $analysis = $this->buildAnalysis($source);

        $diagnostics = $rule->apply($analysis);

        self::assertCount(1, $diagnostics);
        self::assertStringContainsString('foo.bar', $diagnostics[0]->message);
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_does_not_flag_require_used_only_in_a_loaded_file(): void
    {
        $source = <<<PHEL
(ns user
  (:require phel.string :as str)
  (:require phel.json :refer [encode]))

(load "user/helpers")
PHEL;
        $loaded = <<<PHEL
(in-ns user)

(defn shout [s] (encode (str/upper-case s)))
PHEL;

        self::assertSame([], new UnusedRequireRule()->apply($this->buildAnalysisWithLoadedSource($source, $loaded)));
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_still_flags_require_unused_in_the_file_and_its_loaded_files(): void
    {
        $source = <<<PHEL
(ns user
  (:require phel.string :as str))

(load "user/helpers")
PHEL;
        $loaded = <<<PHEL
(in-ns user)

(defn shout [s] s)
PHEL;

        $diagnostics = new UnusedRequireRule()->apply($this->buildAnalysisWithLoadedSource($source, $loaded));

        self::assertCount(1, $diagnostics);
        self::assertSame(LintRuleCodes::UNUSED_REQUIRE, $diagnostics[0]->code);
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_does_not_flag_when_a_load_cannot_be_followed(): void
    {
        $source = <<<PHEL
(ns user
  (:require phel.string :as str))

(load (str "user/" "helpers"))
PHEL;

        self::assertSame([], new UnusedRequireRule()->apply($this->buildAnalysisWithLoadedSource($source, null)));
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_does_not_flag_require_used_inside_a_set_literal(): void
    {
        $source = <<<PHEL
(ns user
  (:require phel.string :as str))

(def words #{(str/upper-case "a")})
PHEL;

        self::assertSame([], new UnusedRequireRule()->apply($this->buildAnalysis($source)));
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_a_type_tag_does_not_count_as_a_require_alias(): void
    {
        $source = <<<PHEL
(ns user
  (:require phel.string))

(defn f [^string s] s)
PHEL;

        self::assertCount(1, new UnusedRequireRule()->apply($this->buildAnalysis($source)));
    }
}
