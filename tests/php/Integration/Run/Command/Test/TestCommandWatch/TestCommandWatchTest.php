<?php

declare(strict_types=1);

namespace PhelTest\Integration\Run\Command\Test\TestCommandWatch;

use Override;
use PhelTest\Integration\Run\Command\Test\FixtureProjectHelper;
use PHPUnit\Framework\TestCase;

final class TestCommandWatchTest extends TestCase
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

    public function test_watch_reruns_tests_when_a_file_changes(): void
    {
        $output = $this->project->runPhelTestWatchCycle('Fixtures/test1.phel');

        self::assertSame(
            2,
            substr_count($output, 'Watching for file changes'),
            'watcher announces idle after the initial run and after the rerun: ' . $output,
        );
        self::assertStringContainsString('Change detected, re-running tests', $output);
        self::assertSame(
            2,
            substr_count($output, 'Passed: 1'),
            'the suite runs twice: ' . $output,
        );
    }
}
