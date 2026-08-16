<?php

declare(strict_types=1);

namespace PhelTest\Integration\Run\Command\Test\TestCommandParallelCompileError;

use Override;
use PhelTest\Integration\Run\Command\Test\FixtureProjectHelper;
use PHPUnit\Framework\TestCase;

use function substr_count;

/**
 * The parallel runner no longer evaluates every namespace in the parent
 * before dispatching (#3203). Two things must still hold on a cold cache,
 * which a fresh fixture project always is:
 *
 *  - a namespace two test files share is compiled once, in the parent,
 *    instead of being raced by two workers;
 *  - a leaf that does not compile is reported once by the worker that hit
 *    it, fails the run, and does not stop the other leaves from running.
 */
final class TestCommandParallelCompileErrorTest extends TestCase
{
    private FixtureProjectHelper $project;

    #[Override]
    protected function setUp(): void
    {
        $this->project = FixtureProjectHelper::setUpProject(__DIR__);
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->project->tearDownProject();
    }

    public function test_a_broken_leaf_fails_the_run_once_while_the_shared_dependency_serves_both_workers(): void
    {
        [$exitCode, $output] = $this->project->runPhelTest(['--parallel=2']);

        self::assertSame(1, $exitCode, $output);
        self::assertStringContainsString('Failed to compile fixture.broken-test', $output);
        self::assertSame(
            1,
            substr_count($output, 'Failed to compile fixture.broken-test'),
            'a compile error is deterministic and must not be retried on fresh workers: ' . $output,
        );
        self::assertStringNotContainsString('Retried:', $output);
        self::assertMatchesRegularExpression('/Passed:\s+2/', $output);
        self::assertMatchesRegularExpression('/Failed:\s+0/', $output);
    }
}
