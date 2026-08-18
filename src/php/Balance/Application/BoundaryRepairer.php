<?php

declare(strict_types=1);

namespace Phel\Balance\Application;

use Phel\Balance\Domain\BalanceReport;

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

        $insertion = $lineStart;
        $newline = str_contains(substr($code, 0, $offset), "\r\n") ? "\r\n" : "\n";
        $previousLine = '';
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
                $previousLine = $line;
                break;
            }

            $cursor = $previousLineStart;
        }

        if (str_contains($previousLine, ';')) {
            $insertion = $lineStart;
            $text = $report->missingClosers() . $newline;
        } else {
            $text = $report->missingClosers();
        }

        return substr($code, 0, $insertion) . $text . substr($code, $insertion);
    }
}
