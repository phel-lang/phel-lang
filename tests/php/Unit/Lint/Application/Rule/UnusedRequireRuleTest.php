<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lint\Application\Rule;

use Phel\Lint\Application\Config\RuleRegistry;
use Phel\Lint\Application\Rule\UnusedRequireRule;
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
        self::assertSame(RuleRegistry::UNUSED_REQUIRE, $diagnostics[0]->code);
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
}
