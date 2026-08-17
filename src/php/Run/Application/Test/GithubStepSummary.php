<?php

declare(strict_types=1);

namespace Phel\Run\Application\Test;

use function file_put_contents;
use function getenv;
use function is_string;
use function sprintf;

use const FILE_APPEND;

/**
 * The one-line run summary GitHub Actions shows on the job page, appended to
 * the file `$GITHUB_STEP_SUMMARY` names. In a serial run the `github`
 * reporter in `phel.test` writes it at `:summary`; in a parallel run every
 * worker would write its own namespace's line, so the workers drop the
 * variable and the parent writes the totals here. Same wording on both sides:
 * `phel test: 10 passed, 1 failed, 0 errors, 2 skipped (13 total)`.
 *
 * @internal
 */
final class GithubStepSummary
{
    private const string ENV = 'GITHUB_STEP_SUMMARY';

    public static function append(Counts $counts): void
    {
        $path = getenv(self::ENV);
        if (!is_string($path) || $path === '') {
            return;
        }

        file_put_contents($path, self::line($counts) . "\n", FILE_APPEND);
    }

    public static function line(Counts $counts): string
    {
        $skipped = $counts->skipped > 0 ? sprintf(', %d skipped', $counts->skipped) : '';

        return sprintf(
            'phel test: %d passed, %d failed, %d errors%s (%d total)',
            $counts->pass,
            $counts->failed,
            $counts->error,
            $skipped,
            $counts->total,
        );
    }
}
