<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lint\Application\Rule;

use Phel\Lint\Application\Config\RuleRegistry;
use Phel\Lint\Application\Rule\UnusedBindingRule;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

final class UnusedBindingRuleTest extends RuleTestCase
{
    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_flags_unused_let_binding(): void
    {
        $rule = new UnusedBindingRule();
        $analysis = $this->buildAnalysis("(let [x 1] 42)\n");

        $diagnostics = $rule->apply($analysis);

        self::assertCount(1, $diagnostics);
        self::assertSame(RuleRegistry::UNUSED_BINDING, $diagnostics[0]->code);
        self::assertStringContainsString("'x'", $diagnostics[0]->message);
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_does_not_flag_used_binding(): void
    {
        $rule = new UnusedBindingRule();
        $analysis = $this->buildAnalysis("(let [x 1] x)\n");

        self::assertSame([], $rule->apply($analysis));
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_ignores_underscore_bindings(): void
    {
        $rule = new UnusedBindingRule();
        $analysis = $this->buildAnalysis("(let [_ 1] 42)\n");

        self::assertSame([], $rule->apply($analysis));
    }
}
