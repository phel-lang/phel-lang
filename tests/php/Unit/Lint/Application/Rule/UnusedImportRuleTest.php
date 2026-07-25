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

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_does_not_flag_a_used_dot_separated_import(): void
    {
        $rule = new UnusedImportRule();
        // `(:use Phel.Lang.Keyword)` binds the alias `Keyword`, exactly as the
        // backslash spelling does.
        $source = <<<PHEL
(ns user
  (:use Phel.Lang.Keyword))

(php/:: Keyword (create "a"))
PHEL;
        $analysis = $this->buildAnalysis($source);

        self::assertSame([], $rule->apply($analysis));
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_flags_an_unused_dot_separated_import(): void
    {
        $rule = new UnusedImportRule();
        $source = <<<PHEL
(ns user
  (:use Phel.Lang.Keyword))

42
PHEL;
        $analysis = $this->buildAnalysis($source);

        $diagnostics = $rule->apply($analysis);

        self::assertCount(1, $diagnostics);
        self::assertStringContainsString('Phel.Lang.Keyword', $diagnostics[0]->message);
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_does_not_flag_a_used_backslash_separated_import(): void
    {
        $rule = new UnusedImportRule();
        $source = <<<PHEL
(ns user
  (:use Phel\\Lang\\Keyword))

(php/:: Keyword (create "a"))
PHEL;
        $analysis = $this->buildAnalysis($source);

        self::assertSame([], $rule->apply($analysis));
    }
}
