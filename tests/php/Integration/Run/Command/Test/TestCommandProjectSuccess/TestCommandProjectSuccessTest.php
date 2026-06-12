<?php

declare(strict_types=1);

namespace PhelTest\Integration\Run\Command\Test\TestCommandProjectSuccess;

use Override;
use PhelTest\Integration\Run\Command\Test\FixtureProjectHelper;
use PHPUnit\Framework\TestCase;

final class TestCommandProjectSuccessTest extends TestCase
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

    public function test_all_in_project(): void
    {
        [$exitCode, $output] = $this->project->runPhelTest([]);

        self::assertSame(0, $exitCode, $output);
        self::assertMatchesRegularExpression('/Passed: 2/', $output);
        self::assertMatchesRegularExpression('/Failed: 0/', $output);
        self::assertMatchesRegularExpression('/Error: 0/', $output);
        self::assertMatchesRegularExpression('/Total: 2/', $output);
    }

    public function test_one_file_in_project(): void
    {
        [$exitCode, $output] = $this->project->runPhelTest(['Fixtures/test1.phel']);

        self::assertSame(0, $exitCode, $output);
        self::assertMatchesRegularExpression('/Passed: 1/', $output);
        self::assertMatchesRegularExpression('/Total: 1/', $output);
    }

    public function test_one_file_outside_configured_directories(): void
    {
        [$exitCode, $output] = $this->project->runPhelTest(['OutsideFixtures/test3.phel']);

        self::assertSame(0, $exitCode, $output);
        self::assertMatchesRegularExpression('/Passed: 1/', $output);
        self::assertMatchesRegularExpression('/Total: 1/', $output);
    }

    public function test_one_file_without_tests_fails(): void
    {
        [$exitCode, $output] = $this->project->runPhelTest(['OutsideFixtures/no-tests.phel']);

        self::assertSame(1, $exitCode, $output);
        self::assertMatchesRegularExpression('/No tests matched the given paths or selectors/', $output);
    }
}
