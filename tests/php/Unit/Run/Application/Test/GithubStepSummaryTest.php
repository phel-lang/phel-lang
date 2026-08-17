<?php

declare(strict_types=1);

namespace PhelTest\Unit\Run\Application\Test;

use Override;
use Phel\Run\Application\Test\Counts;
use Phel\Run\Application\Test\GithubStepSummary;
use PHPUnit\Framework\TestCase;

use function file_get_contents;
use function file_put_contents;
use function getenv;
use function is_string;
use function putenv;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

final class GithubStepSummaryTest extends TestCase
{
    private string|false $previous;

    private string $path;

    #[Override]
    protected function setUp(): void
    {
        $this->previous = getenv('GITHUB_STEP_SUMMARY');
        $this->path = sys_get_temp_dir() . '/phel-step-summary-' . uniqid() . '.md';
    }

    #[Override]
    protected function tearDown(): void
    {
        putenv(is_string($this->previous) ? 'GITHUB_STEP_SUMMARY=' . $this->previous : 'GITHUB_STEP_SUMMARY');
        @unlink($this->path);
    }

    public function test_the_line_matches_what_the_phel_reporter_writes(): void
    {
        self::assertSame(
            'phel test: 10 passed, 1 failed, 1 errors, 2 skipped (12 total)',
            GithubStepSummary::line(new Counts(pass: 10, failed: 1, error: 1, skipped: 2, total: 12)),
        );
        self::assertSame(
            'phel test: 3 passed, 0 failed, 0 errors (3 total)',
            GithubStepSummary::line(new Counts(pass: 3, total: 3)),
        );
    }

    public function test_it_appends_to_the_file_the_env_var_names(): void
    {
        putenv('GITHUB_STEP_SUMMARY=' . $this->path);
        file_put_contents($this->path, "earlier step\n");

        GithubStepSummary::append(new Counts(pass: 3, total: 3));

        self::assertSame("earlier step\nphel test: 3 passed, 0 failed, 0 errors (3 total)\n", file_get_contents($this->path));
    }

    public function test_without_the_env_var_it_writes_nothing(): void
    {
        putenv('GITHUB_STEP_SUMMARY');

        GithubStepSummary::append(new Counts(pass: 3, total: 3));

        self::assertFileDoesNotExist($this->path);
    }
}
