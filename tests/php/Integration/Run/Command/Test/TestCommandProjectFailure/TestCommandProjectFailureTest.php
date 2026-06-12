<?php

declare(strict_types=1);

namespace PhelTest\Integration\Run\Command\Test\TestCommandProjectFailure;

use Override;
use PhelTest\Integration\Run\Command\Test\FixtureProjectHelper;
use PHPUnit\Framework\TestCase;

final class TestCommandProjectFailureTest extends TestCase
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

    public function test_all_in_failed_project(): void
    {
        [$exitCode, $output] = $this->project->runPhelTest([]);

        self::assertSame(1, $exitCode, $output);
        self::assertMatchesRegularExpression('/Passed: 0/', $output);
        self::assertMatchesRegularExpression('/Failed: 1/', $output);
        self::assertMatchesRegularExpression('/Error: 0/', $output);
        self::assertMatchesRegularExpression('/Total: 1/', $output);
    }
}
