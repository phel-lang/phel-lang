<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lint\Application\Formatter;

use Phel\Api\Transfer\Diagnostic;
use Phel\Lint\Application\Formatter\JsonFormatter;
use Phel\Lint\Transfer\LintResult;
use PHPUnit\Framework\TestCase;

use function json_decode;

final class JsonFormatterTest extends TestCase
{
    public function test_it_serialises_diagnostics_as_stable_json_array(): void
    {
        $formatter = new JsonFormatter();
        $result = new LintResult([
            new Diagnostic('phel/a', Diagnostic::SEVERITY_ERROR, 'msg', '/f.phel', 2, 4, 2, 6),
        ]);

        $decoded = json_decode($formatter->format($result), true);

        self::assertIsArray($decoded);
        self::assertCount(1, $decoded);
        self::assertSame('phel/a', $decoded[0]['code']);
        self::assertSame(Diagnostic::SEVERITY_ERROR, $decoded[0]['severity']);
        self::assertSame(2, $decoded[0]['startLine']);
    }

    public function test_it_returns_empty_array_for_clean_run(): void
    {
        $formatter = new JsonFormatter();

        self::assertSame('[]', $formatter->format(new LintResult([])));
    }
}
