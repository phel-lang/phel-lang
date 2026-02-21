<?php

declare(strict_types=1);

namespace PhelTest\Unit\Build\Domain\Extractor;

use Phel\Build\Domain\Extractor\FirstFormExtractor;
use PHPUnit\Framework\TestCase;

final class FirstFormExtractorTest extends TestCase
{
    private FirstFormExtractor $extractor;

    protected function setUp(): void
    {
        $this->extractor = new FirstFormExtractor();
    }

    public function test_simple_ns_form(): void
    {
        $code = '(ns my\\namespace)';

        self::assertSame('(ns my\\namespace)', $this->extractor->extract($code));
    }

    public function test_ns_form_followed_by_more_code(): void
    {
        $code = '(ns my\\namespace) (def x 1)';

        self::assertSame('(ns my\\namespace)', $this->extractor->extract($code));
    }

    public function test_nested_parentheses_in_ns_form(): void
    {
        $code = '(ns my\\namespace (require phel\\core :refer [map filter])) (def x 1)';

        self::assertSame(
            '(ns my\\namespace (require phel\\core :refer [map filter]))',
            $this->extractor->extract($code),
        );
    }

    public function test_string_containing_parentheses_not_counted(): void
    {
        $code = '(ns my\\namespace (doc "has (parens) inside")) (def x 1)';

        self::assertSame(
            '(ns my\\namespace (doc "has (parens) inside"))',
            $this->extractor->extract($code),
        );
    }

    public function test_string_with_escaped_quotes(): void
    {
        $code = '(ns my\\namespace (doc "has \\"escaped\\" quotes")) (def x 1)';

        self::assertSame(
            '(ns my\\namespace (doc "has \\"escaped\\" quotes"))',
            $this->extractor->extract($code),
        );
    }

    public function test_comment_before_ns_form(): void
    {
        $code = "# This is a comment\n(ns my\\namespace)";

        self::assertSame("# This is a comment\n(ns my\\namespace)", $this->extractor->extract($code));
    }

    public function test_comment_containing_parentheses_not_counted(): void
    {
        $code = "# (not a form)\n(ns my\\namespace) (def x 1)";

        self::assertSame("# (not a form)\n(ns my\\namespace)", $this->extractor->extract($code));
    }

    public function test_empty_file(): void
    {
        self::assertSame('', $this->extractor->extract(''));
    }

    public function test_unclosed_parenthesis_returns_full_code(): void
    {
        $code = '(ns my\\namespace';

        self::assertSame($code, $this->extractor->extract($code));
    }

    public function test_leading_whitespace(): void
    {
        $code = "  \n  (ns my\\namespace) (def x 1)";

        self::assertSame("  \n  (ns my\\namespace)", $this->extractor->extract($code));
    }

    public function test_multiple_top_level_forms_returns_first_only(): void
    {
        $code = "(first-form a b)\n(second-form c d)\n(third-form e f)";

        self::assertSame('(first-form a b)', $this->extractor->extract($code));
    }
}
