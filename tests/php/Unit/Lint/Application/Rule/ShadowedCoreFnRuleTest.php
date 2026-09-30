<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lint\Application\Rule;

use Phel\Lint\Application\Rule\ShadowedCoreFnRule;
use Phel\Lint\Domain\CoreFunctionNamesInterface;
use Phel\Shared\Api\Diagnostic;
use Phel\Shared\LintRuleCodes;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

use function in_array;

final class ShadowedCoreFnRuleTest extends RuleTestCase
{
    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_flags_a_let_binding_named_after_a_core_fn(): void
    {
        $diagnostics = $this->rule()->apply($this->buildAnalysis("(let [inc (fn [x] 99)]\n  (inc 1))\n"));

        self::assertCount(1, $diagnostics);
        self::assertSame(LintRuleCodes::SHADOWED_CORE_FN, $diagnostics[0]->code);
        self::assertStringContainsString("'inc'", $diagnostics[0]->message);
        self::assertSame(1, $diagnostics[0]->startLine);
        self::assertSame(6, $diagnostics[0]->startCol);
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_flags_loop_bindings(): void
    {
        $diagnostics = $this->rule()->apply($this->buildAnalysis("(loop [count 0] (recur count))\n"));

        self::assertCount(1, $diagnostics);
        self::assertStringContainsString("'count'", $diagnostics[0]->message);
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_flags_fn_and_defn_params(): void
    {
        $analysis = $this->buildAnalysis("(defn f [first & rest] first)\n(fn [count] count)\n");

        $names = $this->flaggedNames($this->rule()->apply($analysis));

        self::assertSame(['first', 'rest', 'count'], $names);
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_flags_every_arity_of_a_multi_arity_fn(): void
    {
        $analysis = $this->buildAnalysis("(defn f ([first] first) ([first rest] rest))\n");

        self::assertSame(['first', 'first', 'rest'], $this->flaggedNames($this->rule()->apply($analysis)));
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_flags_an_iteration_binding(): void
    {
        $analysis = $this->buildAnalysis("(for [first :in [1 2]] first)\n");

        self::assertSame(['first'], $this->flaggedNames($this->rule()->apply($analysis)));
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_ignores_names_that_are_not_core_fns(): void
    {
        $analysis = $this->buildAnalysis("(let [x 1 total 2] (fn [y] (+ x y total)))\n");

        self::assertSame([], $this->rule()->apply($analysis));
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_ignores_binding_values_and_fn_names(): void
    {
        $analysis = $this->buildAnalysis("(let [x inc] (fn count [y] (x y)))\n");

        self::assertSame([], $this->rule()->apply($analysis));
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_ignores_destructured_names(): void
    {
        $analysis = $this->buildAnalysis("(let [[first] [1] {:keys [count]} {}] first)\n");

        self::assertSame([], $this->rule()->apply($analysis));
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_ignores_quoted_forms(): void
    {
        $analysis = $this->buildAnalysis("(quote (let [inc 1] inc))\n");

        self::assertSame([], $this->rule()->apply($analysis));
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_finds_bindings_nested_in_a_binding_value(): void
    {
        $analysis = $this->buildAnalysis("(let [x (let [first 1] first)] x)\n");

        self::assertSame(['first'], $this->flaggedNames($this->rule()->apply($analysis)));
    }

    private function rule(): ShadowedCoreFnRule
    {
        return new ShadowedCoreFnRule(new class() implements CoreFunctionNamesInterface {
            public function contains(string $name): bool
            {
                return in_array($name, ['inc', 'count', 'first', 'rest'], true);
            }
        });
    }

    /**
     * @param list<Diagnostic> $diagnostics
     *
     * @return list<string>
     */
    private function flaggedNames(array $diagnostics): array
    {
        $names = [];
        foreach ($diagnostics as $diagnostic) {
            self::assertSame(1, preg_match("/^Binding '([^']+)'/", $diagnostic->message, $matches));
            $names[] = $matches[1];
        }

        return $names;
    }
}
