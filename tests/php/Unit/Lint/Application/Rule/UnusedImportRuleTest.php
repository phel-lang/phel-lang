<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lint\Application\Rule;

use Phel\Lint\Application\Config\RuleRegistry;
use Phel\Lint\Application\Rule\UnusedImportRule;
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
        self::assertSame(RuleRegistry::UNUSED_IMPORT, $diagnostics[0]->code);
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
}
