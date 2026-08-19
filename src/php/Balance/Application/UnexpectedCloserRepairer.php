<?php

declare(strict_types=1);

namespace Phel\Balance\Application;

use Phel\Balance\Domain\BalanceReport;
use Phel\Balance\Domain\OpenDelimiter;

use function count;
use function strlen;

/**
 * @internal
 */
final readonly class UnexpectedCloserRepairer
{
    public function repair(string $code, BalanceReport $report): ?string
    {
        if (count($report->unexpectedClosers) !== 1) {
            return null;
        }

        $unexpected = $report->unexpectedClosers[0];
        // A mismatched closer is ambiguous: changing either the closer or its
        // opener can produce balanced, valid Phel. Never guess between them.
        if ($unexpected->openDelimiter instanceof OpenDelimiter || $report->unclosed !== []) {
            return null;
        }

        return substr($code, 0, $unexpected->offset)
            . substr($code, $unexpected->offset + strlen($unexpected->closerText));
    }
}
