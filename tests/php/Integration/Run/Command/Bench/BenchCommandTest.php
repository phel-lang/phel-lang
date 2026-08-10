<?php

declare(strict_types=1);

namespace PhelTest\Integration\Run\Command\Bench;

use Override;
use PhelTest\Integration\Run\Command\Test\FixtureProjectHelper;
use PHPUnit\Framework\TestCase;

use function json_encode;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

final class BenchCommandTest extends TestCase
{
    private FixtureProjectHelper $project;

    /** @var list<string> */
    private array $baselineFiles = [];

    #[Override]
    protected function setUp(): void
    {
        $this->project = FixtureProjectHelper::setUpProject(__DIR__);
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->project->tearDownProject();

        foreach ($this->baselineFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }

    public function test_it_runs_every_benchmark_in_the_project(): void
    {
        [$exitCode, $output] = $this->project->runPhelCommand('bench', []);

        self::assertSame(0, $exitCode, $output);
        self::assertStringContainsString('fixtures.bench1/bench-addition', $output);
        self::assertStringContainsString('fixtures.bench1/bench-vector-build', $output);
        self::assertStringContainsString('rstdev', $output);
    }

    public function test_it_reports_a_benchmark_without_a_baseline_as_new(): void
    {
        [$exitCode, $output] = $this->project->runPhelCommand('bench', []);

        self::assertSame(0, $exitCode, $output);
        self::assertStringContainsString('new', $output);
    }

    public function test_it_applies_the_name_filter(): void
    {
        [$exitCode, $output] = $this->project->runPhelCommand('bench', ['--filter=addition']);

        self::assertSame(0, $exitCode, $output);
        self::assertStringContainsString('bench-addition', $output);
        self::assertStringNotContainsString('bench-vector-build', $output);
    }

    public function test_it_honours_the_revs_and_iterations_options(): void
    {
        [$exitCode, $output] = $this->project->runPhelCommand(
            'bench',
            ['--filter=addition', '--revs=3', '--iterations=2', '--warmup=0'],
        );

        self::assertSame(0, $exitCode, $output);
        // The flags win over the `{:revs 1 :iterations 1}` the benchmark asks
        // for: an option a file could silently override is worse than no option.
        self::assertMatchesRegularExpression('/bench-addition\s+3\s+2\s/', $output);
    }

    public function test_it_writes_and_reuses_a_baseline(): void
    {
        $baseline = $this->baselinePath();

        [$storeExit, $storeOutput] = $this->project->runPhelCommand('bench', ['--store=' . $baseline]);
        self::assertSame(0, $storeExit, $storeOutput);
        self::assertStringContainsString('Baseline written to', $storeOutput);
        self::assertFileExists($baseline);

        [$refExit, $refOutput] = $this->project->runPhelCommand('bench', ['--ref=' . $baseline]);
        self::assertSame(0, $refExit, $refOutput);
        self::assertStringNotContainsString('new', $refOutput);
    }

    public function test_it_fails_when_a_benchmark_is_slower_than_the_tolerance(): void
    {
        // A hand-written baseline rather than a stored one: a real previous run
        // would make the verdict depend on how fast the machine is that day.
        $baseline = $this->baselinePath();
        file_put_contents($baseline, json_encode([
            'fixtures.bench1/bench-addition' => 1,
            'fixtures.bench1/bench-vector-build' => 1,
        ]));

        [$exitCode, $output] = $this->project->runPhelCommand(
            'bench',
            ['--ref=' . $baseline, '--tolerance=1'],
        );

        self::assertSame(1, $exitCode, $output);
        self::assertStringContainsString('Slower than the baseline', $output);
    }

    public function test_it_succeeds_when_every_benchmark_is_within_the_tolerance(): void
    {
        $baseline = $this->baselinePath();
        file_put_contents($baseline, json_encode([
            'fixtures.bench1/bench-addition' => 1_000_000_000,
            'fixtures.bench1/bench-vector-build' => 1_000_000_000,
        ]));

        [$exitCode, $output] = $this->project->runPhelCommand(
            'bench',
            ['--ref=' . $baseline, '--tolerance=1'],
        );

        self::assertSame(0, $exitCode, $output);
        self::assertStringNotContainsString('Slower than the baseline', $output);
    }

    public function test_it_ignores_a_missing_baseline_file(): void
    {
        [$exitCode, $output] = $this->project->runPhelCommand(
            'bench',
            ['--ref=' . $this->baselinePath(), '--tolerance=1'],
        );

        self::assertSame(0, $exitCode, $output);
        self::assertStringContainsString('new', $output);
    }

    private function baselinePath(): string
    {
        $path = sys_get_temp_dir() . '/phel-bench-baseline-' . uniqid() . '.json';
        $this->baselineFiles[] = $path;

        return $path;
    }
}
