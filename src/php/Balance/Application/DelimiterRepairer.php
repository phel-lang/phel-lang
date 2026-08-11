<?php

declare(strict_types=1);

namespace Phel\Balance\Application;

use Phel\Balance\Domain\BalanceReport;

use function rtrim;
use function str_ends_with;

/**
 * Appends the closers a {@see BalanceReport} found missing.
 *
 * Only ever appends. It never deletes a delimiter and never inserts one in the
 * middle of a file, which keeps the rewrite reviewable as a diff at the end of
 * the file.
 *
 * @internal
 */
final readonly class DelimiterRepairer
{
    /**
     * Anchors the closers at the end of the last non-blank line so the repaired
     * form reads the way a human would have closed it. When that line is a `;`
     * comment the closers go on their own line instead: appended inside the
     * comment they would be text, and the file would still not parse.
     */
    public function repair(string $code, BalanceReport $report): string
    {
        $closers = $report->missingClosers();
        $trailingNewline = str_ends_with($code, "\n") ? "\n" : '';

        if ($report->endsInLineComment) {
            return rtrim($code, "\r\n") . "\n" . $closers . $trailingNewline;
        }

        return rtrim($code) . $closers . $trailingNewline;
    }
}
