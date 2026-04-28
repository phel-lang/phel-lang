<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lint\Application\Rule;

use Phel\Lint\Application\Config\RuleRegistry;
use Phel\Lint\Application\Rule\ArityMismatchRule;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

final class ArityMismatchRuleTest extends RuleTestCase
{
    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_flags_wrong_arity_for_same_file_defn(): void
    {
        $rule = new ArityMismatchRule();
        $source = <<<PHEL
(ns user)

(defn add [x y] (+ x y))

(add 1 2 3)
PHEL;
        $analysis = $this->buildAnalysis($source);

        $diagnostics = $rule->apply($analysis);

        self::assertNotEmpty($diagnostics);
        self::assertSame(RuleRegistry::ARITY_MISMATCH, $diagnostics[0]->code);
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_allows_variadic_arity(): void
    {
        $rule = new ArityMismatchRule();
        $source = <<<PHEL
(ns user)

(defn vara [x & rest] x)

(vara 1 2 3 4)
PHEL;
        $analysis = $this->buildAnalysis($source);

        self::assertSame([], $rule->apply($analysis));
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_does_not_flag_correct_arity(): void
    {
        $rule = new ArityMismatchRule();
        $source = <<<PHEL
(ns user)

(defn add [x y] (+ x y))

(add 1 2)
PHEL;
        $analysis = $this->buildAnalysis($source);

        self::assertSame([], $rule->apply($analysis));
    }
}
