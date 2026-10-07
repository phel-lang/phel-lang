<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lint\Application\Rule;

use Phel\Lint\Application\Rule\DuplicateKeyRule;
use Phel\Lint\Domain\FileAnalysis;
use Phel\Shared\Api\ProjectIndex;
use Phel\Shared\LintRuleCodes;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

final class DuplicateKeyRuleTest extends RuleTestCase
{
    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_flags_duplicate_symbol_keys(): void
    {
        $rule = new DuplicateKeyRule($this->compilerFacade());
        $analysis = new FileAnalysis(
            uri: 'test.phel',
            namespace: '',
            source: "{x 1 x 2}\n",
            forms: [],
            projectIndex: new ProjectIndex([], []),
        );

        $diagnostics = $rule->apply($analysis);

        self::assertNotEmpty($diagnostics);
        self::assertSame(LintRuleCodes::DUPLICATE_KEY, $diagnostics[0]->code);
        self::assertStringContainsString('x', $diagnostics[0]->message);
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_leaves_duplicate_constant_keys_to_the_reader(): void
    {
        $rule = new DuplicateKeyRule($this->compilerFacade());
        $analysis = new FileAnalysis(
            uri: 'test.phel',
            namespace: '',
            source: "{:a 1 :a 2 \"x\" 3 \"x\" 4}\n",
            forms: [],
            projectIndex: new ProjectIndex([], []),
        );

        self::assertSame([], $rule->apply($analysis));
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
