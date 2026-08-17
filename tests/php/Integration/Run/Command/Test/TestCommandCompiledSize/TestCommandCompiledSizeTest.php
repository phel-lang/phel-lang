<?php

declare(strict_types=1);

namespace PhelTest\Integration\Run\Command\Test\TestCommandCompiledSize;

use Override;
use PhelTest\Integration\Run\Command\Test\FixtureProjectHelper;
use PHPUnit\Framework\TestCase;

use function intdiv;
use function sprintf;
use function strlen;

/**
 * How much PHP one `(is ...)` compiles to. A cold `phel test` is compile
 * time, and a test namespace is almost entirely `is` forms, so the size of
 * one assertion site is what a cold run costs (#3212). Before the built-in
 * arms moved their report maps into runtime helpers, `(is (= 1 1))` was
 * ~3.7 KB of PHP; this pins the ceiling so it cannot silently grow back.
 */
final class TestCommandCompiledSizeTest extends TestCase
{
    private const int MAX_BYTES_PER_ASSERTION = 1400;

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

    public function test_one_is_form_compiles_to_a_bounded_amount_of_php(): void
    {
        [$exitCode, $output] = $this->project->runPhelTest([]);
        self::assertSame(0, $exitCode, $output);
        self::assertMatchesRegularExpression('/Passed:\s*22/', $output);

        $one = strlen($this->project->compiledCodeOf('app.one-assertion-test'));
        $many = strlen($this->project->compiledCodeOf('app.many-assertions-test'));
        $perAssertion = intdiv($many - $one, 20);

        self::assertLessThan(
            self::MAX_BYTES_PER_ASSERTION,
            $perAssertion,
            sprintf('one (is (= 1 1)) compiles to %d bytes of PHP', $perAssertion),
        );
    }
}
