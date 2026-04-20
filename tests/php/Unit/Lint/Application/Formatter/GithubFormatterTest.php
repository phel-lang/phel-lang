<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lint\Application\Formatter;

use Phel\Api\Transfer\Diagnostic;
use Phel\Lint\Application\Formatter\GithubFormatter;
use Phel\Lint\Transfer\LintResult;
use PHPUnit\Framework\TestCase;

final class GithubFormatterTest extends TestCase
{
    public function test_it_emits_github_annotation_command_per_diagnostic(): void
    {
        $formatter = new GithubFormatter();
        $result = new LintResult([
            new Diagnostic('phel/a', Diagnostic::SEVERITY_ERROR, 'boom', '/f.phel', 7, 3, 7, 9),
        ]);

        $out = $formatter->format($result);

        self::assertStringStartsWith('::error ', $out);
        self::assertStringContainsString('file=/f.phel', $out);
        self::assertStringContainsString('line=7', $out);
        self::assertStringContainsString('col=3', $out);
        self::assertStringContainsString('title=phel/a', $out);
        self::assertStringContainsString('::boom', $out);
    }

    public function test_it_maps_warning_severity_to_warning_level(): void
    {
        $formatter = new GithubFormatter();
        $result = new LintResult([
            new Diagnostic('phel/b', Diagnostic::SEVERITY_WARNING, 'x', 'f.phel', 1, 1, 1, 2),
        ]);

        self::assertStringStartsWith('::warning ', $formatter->format($result));
    }

    public function test_it_maps_info_severity_to_notice_level(): void
    {
        $formatter = new GithubFormatter();
        $result = new LintResult([
            new Diagnostic('phel/c', Diagnostic::SEVERITY_INFO, 'x', 'f.phel', 1, 1, 1, 2),
        ]);

        self::assertStringStartsWith('::notice ', $formatter->format($result));
    }

    public function test_it_encodes_special_characters_in_messages(): void
    {
        $formatter = new GithubFormatter();
        $result = new LintResult([
            new Diagnostic('phel/d', Diagnostic::SEVERITY_WARNING, "one\ntwo%", 'f.phel', 1, 1, 1, 2),
        ]);

        $out = $formatter->format($result);

        self::assertStringContainsString('one%0Atwo%25', $out);
    }
}
