<?php

declare(strict_types=1);

namespace Phel\Balance\Application;

use Phel\Balance\Domain\BalanceReport;

use function str_contains;
use function strrpos;
use function substr;
use function trim;

/**
 * @internal
 */
final readonly class BoundaryRepairer
{
    public function repair(string $code, BalanceReport $report): ?string
    {
        if ($report->topLevelFormOffsetAfterUnclosed === null || $report->unclosed === []) {
            return null;
        }

        $offset = $report->topLevelFormOffsetAfterUnclosed;
        $lineStart = strrpos(substr($code, 0, $offset), "\n");
        $lineStart = $lineStart === false ? 0 : $lineStart + 1;

        $newline = str_contains(substr($code, 0, $offset), "\r\n") ? "\r\n" : "\n";

        // Walk back over blank lines to the preceding non-blank line. `$lineEnd`
        // ends up at the LF that terminates that line (the CR, if any, sits at
        // `$lineEnd - 1`), and `$insertion` at the end of its content.
        $insertion = $lineStart;
        $precedingLineNewline = null;
        $cursor = $lineStart;
        while ($cursor > 0) {
            $lineEnd = $cursor - 1;
            $previousLineStart = strrpos(substr($code, 0, $lineEnd), "\n");
            $previousLineStart = $previousLineStart === false ? 0 : $previousLineStart + 1;
            $line = substr($code, $previousLineStart, max(0, $lineEnd - $previousLineStart));
            if (trim($line) !== '') {
                // Keep CRLF intact: lineEnd points at the LF, so insert before
                // the preceding CR rather than between the two bytes.
                $insertion = $lineEnd > 0 && $code[$lineEnd - 1] === "\r"
                    ? $lineEnd - 1
                    : $lineEnd;
                $precedingLineNewline = $lineEnd;
                break;
            }

            $cursor = $previousLineStart;
        }

        if ($report->precedingLineIsComment && $precedingLineNewline !== null) {
            // The preceding line ends in a real `;` comment, so the closers go on
            // their own line after it rather than inside the comment text. Insert
            // past the comment line's newline; a following blank line's line
            // break terminates the closer line, otherwise we add one.
            $insertion = $precedingLineNewline + 1;
            $after = substr($code, $insertion, 1);
            $text = $after === "\n" || $after === "\r"
                ? $report->missingClosers()
                : $report->missingClosers() . $newline;
        } else {
            $text = $report->missingClosers();
        }

        return substr($code, 0, $insertion) . $text . substr($code, $insertion);
    }
}
