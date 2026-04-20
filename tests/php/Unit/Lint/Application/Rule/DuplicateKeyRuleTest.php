<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lint\Application\Rule;

use Phel\Lint\Application\Config\RuleRegistry;
use Phel\Lint\Application\Rule\DuplicateKeyRule;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

final class DuplicateKeyRuleTest extends RuleTestCase
{
    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_flags_duplicate_keyword_keys(): void
    {
        $rule = new DuplicateKeyRule($this->compilerFacade());
        $analysis = $this->buildAnalysis("{:a 1 :a 2}\n");

        $diagnostics = $rule->apply($analysis);

        self::assertNotEmpty($diagnostics);
        self::assertSame(RuleRegistry::DUPLICATE_KEY, $diagnostics[0]->code);
        self::assertStringContainsString(':a', $diagnostics[0]->message);
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_flags_duplicate_string_keys(): void
    {
        $rule = new DuplicateKeyRule($this->compilerFacade());
        $analysis = $this->buildAnalysis("{\"x\" 1 \"x\" 2}\n");

        self::assertNotEmpty($rule->apply($analysis));
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_does_not_flag_distinct_keys(): void
    {
        $rule = new DuplicateKeyRule($this->compilerFacade());
        $analysis = $this->buildAnalysis("{:a 1 :b 2}\n");

        self::assertSame([], $rule->apply($analysis));
    }
}
