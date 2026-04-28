<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lint\Application\Rule;

use Phel\Lint\Application\Config\RuleRegistry;
use Phel\Lint\Application\Rule\InvalidDestructuringRule;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

final class InvalidDestructuringRuleTest extends RuleTestCase
{
    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_flags_odd_binding_vector(): void
    {
        $rule = new InvalidDestructuringRule();
        $analysis = $this->buildAnalysis("(let [x 1 y] x)\n");

        $diagnostics = $rule->apply($analysis);

        self::assertCount(1, $diagnostics);
        self::assertSame(RuleRegistry::INVALID_DESTRUCTURING, $diagnostics[0]->code);
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_flags_invalid_variadic_marker_in_fn(): void
    {
        $rule = new InvalidDestructuringRule();
        $analysis = $this->buildAnalysis("(fn [a & b c] a)\n");

        self::assertNotEmpty($rule->apply($analysis));
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_accepts_well_formed_variadic_fn(): void
    {
        $rule = new InvalidDestructuringRule();
        $analysis = $this->buildAnalysis("(fn [a & rest] a)\n");

        self::assertSame([], $rule->apply($analysis));
    }
}
