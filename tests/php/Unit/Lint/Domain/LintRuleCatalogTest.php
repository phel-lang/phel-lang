<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lint\Domain;

use Phel\Lint\Domain\LintRuleCatalog;
use Phel\Lint\Domain\LintRuleExplanation;
use Phel\Shared\LintRuleCodes;
use PHPUnit\Framework\TestCase;

final class LintRuleCatalogTest extends TestCase
{
    public function test_every_rule_code_has_an_entry(): void
    {
        foreach ([...LintRuleCodes::allCodes(), LintRuleCodes::INTERNAL_ERROR] as $code) {
            $entry = LintRuleCatalog::find($code);

            self::assertInstanceOf(LintRuleExplanation::class, $entry, $code);
            self::assertSame($code, $entry->code);
            self::assertNotSame('', $entry->title, $code);
            self::assertNotSame('', $entry->fix, $code);
        }
    }

    public function test_the_catalog_lists_nothing_but_known_codes(): void
    {
        $known = [...LintRuleCodes::allCodes(), LintRuleCodes::INTERNAL_ERROR];

        foreach (LintRuleCatalog::all() as $entry) {
            self::assertContains($entry->code, $known);
        }
    }

    public function test_the_prefix_and_case_are_optional(): void
    {
        self::assertSame(LintRuleCodes::UNUSED_REQUIRE, LintRuleCatalog::find('unused-require')?->code);
        self::assertSame(LintRuleCodes::UNUSED_REQUIRE, LintRuleCatalog::find(' Phel/Unused-Require ')?->code);
    }

    public function test_an_unknown_rule_is_null(): void
    {
        self::assertNull(LintRuleCatalog::find('phel/no-such-rule'));
        self::assertNull(LintRuleCatalog::find('PHEL001'));
    }
}
