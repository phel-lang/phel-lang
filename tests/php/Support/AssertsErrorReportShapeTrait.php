<?php

declare(strict_types=1);

namespace PhelTest\Support;

use PHPUnit\Framework\Assert;

use function array_fill_keys;
use function array_search;
use function implode;
use function preg_match;
use function preg_split;
use function sprintf;
use function trim;

/**
 * Asserts the one report every uncaught runtime error gets, whichever command
 * printed it (#3264):
 *
 *     <message>
 *       at <file:line>
 *       data: <map of an uncaught ex-info>
 *     <user-visible frames>
 *        ... N internal frames (<way out>)
 *     hint: <when one matches>
 *
 * Every section but the message and the `at` line is optional, and none of
 * them may appear twice or out of order.
 */
trait AssertsErrorReportShapeTrait
{
    private const array ERROR_REPORT_SECTIONS = [
        'at' => '~^  at \S~',
        'data' => '~^  data: \S~',
        'frame' => '~^#\d+ \S~',
        'collapsed' => '~^   \.\.\. \d+ internal frames? \(.+\)$~',
        'hint' => '~^hint: \S~',
    ];

    /** The order the sections are allowed to appear in. */
    private const array ERROR_REPORT_ORDER = ['message', 'at', 'data', 'frame', 'collapsed', 'hint'];

    /** What every line a REPL session echoes back starts with. */
    private const string REPL_PROMPT_PREFIX = 'user:';

    protected static function assertErrorReportShape(string $report): void
    {
        $lines = preg_split('~\R~', trim($report)) ?: [];
        $counts = array_fill_keys(self::ERROR_REPORT_ORDER, 0);
        $previousRank = 0;

        foreach ($lines as $index => $line) {
            $section = $index === 0 ? 'message' : self::errorReportSection($line);
            Assert::assertNotNull($section, sprintf("Line %d belongs to no section:\n%s\n\n%s", $index, $line, $report));

            $rank = (int) array_search($section, self::ERROR_REPORT_ORDER, true);
            Assert::assertGreaterThanOrEqual(
                $previousRank,
                $rank,
                sprintf("The '%s' section is out of order at line %d:\n\n%s", $section, $index, $report),
            );

            $previousRank = $rank;
            ++$counts[$section];
        }

        Assert::assertSame(1, $counts['at'], sprintf("Exactly one `at` line is expected:\n\n%s", $report));
        Assert::assertLessThanOrEqual(1, $counts['data'], sprintf("At most one `data:` line is expected:\n\n%s", $report));
        Assert::assertLessThanOrEqual(
            1,
            $counts['collapsed'],
            sprintf("Internal frames belong in a single collapsed marker:\n\n%s", $report),
        );
        Assert::assertLessThanOrEqual(1, $counts['hint'], sprintf("At most one `hint:` line is expected:\n\n%s", $report));
    }

    /**
     * Cuts the report out of a REPL transcript, which wraps it in the prompt
     * lines the session echoes back.
     */
    protected static function errorReportFromReplTranscript(string $transcript): string
    {
        $report = [];

        foreach (preg_split('~\R~', trim($transcript)) ?: [] as $line) {
            if (!str_starts_with($line, self::REPL_PROMPT_PREFIX)) {
                $report[] = $line;
                continue;
            }

            if ($report !== []) {
                break;
            }
        }

        return implode("\n", $report);
    }

    private static function errorReportSection(string $line): ?string
    {
        foreach (self::ERROR_REPORT_SECTIONS as $section => $pattern) {
            if (preg_match($pattern, $line) === 1) {
                return $section;
            }
        }

        return null;
    }
}
