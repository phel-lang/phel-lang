<?php

declare(strict_types=1);

namespace PhelTest\Integration\Run\Command\Test\TestCommandThrowingTest;

use Override;
use PhelTest\Integration\Run\Command\Test\FixtureProjectHelper;
use PHPUnit\Framework\TestCase;

final class TestCommandThrowingTestTest extends TestCase
{
    private FixtureProjectHelper $project;

    #[Override]
    protected function setUp(): void
    {
        $this->project = FixtureProjectHelper::setUpProject(__DIR__, shareStdlibCache: true);
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->project->tearDownProject();
    }

    public function test_a_throwing_test_is_an_error_and_the_serial_run_continues(): void
    {
        [$exitCode, $output] = $this->project->runPhelTest([]);

        self::assertSame(1, $exitCode, $output);
        self::assertStringContainsString('a-throws', $output);
        self::assertStringContainsString('boom', $output);
        self::assertMatchesRegularExpression('/Passed: 1/', $output);
        self::assertMatchesRegularExpression('/Failed: 0/', $output);
        self::assertMatchesRegularExpression('/Error: 1/', $output);
        self::assertMatchesRegularExpression('/Total: 2/', $output);
    }
}
