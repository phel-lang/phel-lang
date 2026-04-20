<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lint\Application\Rule;

use Phel\Lint\Application\Config\RuleRegistry;
use Phel\Lint\Application\Rule\RedundantDoRule;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

final class RedundantDoRuleTest extends RuleTestCase
{
    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_flags_do_with_zero_body_forms(): void
    {
        $rule = new RedundantDoRule();
        $analysis = $this->buildAnalysis("(do)\n");

        $diagnostics = $rule->apply($analysis);

        self::assertCount(1, $diagnostics);
        self::assertSame(RuleRegistry::REDUNDANT_DO, $diagnostics[0]->code);
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_flags_do_with_single_body_form(): void
    {
        $rule = new RedundantDoRule();
        $analysis = $this->buildAnalysis("(do 1)\n");

        self::assertCount(1, $rule->apply($analysis));
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_does_not_flag_multi_form_do(): void
    {
        $rule = new RedundantDoRule();
        $analysis = $this->buildAnalysis("(do 1 2 3)\n");

        self::assertSame([], $rule->apply($analysis));
    }
}
