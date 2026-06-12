<?php

declare(strict_types=1);

namespace PhelTest\Unit\Run\Infrastructure\Command;

use Phel\Run\Domain\Test\TestCommandOptions;
use Phel\Run\Infrastructure\Command\TestCommand;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Symfony\Component\Console\Command\Command;

final class TestCommandFilterNoMatchesTest extends TestCase
{
    // --- isNoMatchWithSelectors ---

    public function test_no_selector_zero_total_is_not_a_no_match(): void
    {
        self::assertFalse($this->isNoMatch(0, []));
    }

    public function test_filter_selector_zero_total_is_a_no_match(): void
    {
        self::assertTrue($this->isNoMatch(0, [
            TestCommandOptions::FILTERS => ['zzz-no-such-test'],
        ]));
    }

    public function test_ns_selector_zero_total_is_a_no_match(): void
    {
        self::assertTrue($this->isNoMatch(0, [
            TestCommandOptions::NS_PATTERNS => ['zzz.**'],
        ]));
    }

    public function test_include_selector_zero_total_is_a_no_match(): void
    {
        self::assertTrue($this->isNoMatch(0, [
            TestCommandOptions::INCLUDE => ['integration'],
        ]));
    }

    public function test_exclude_selector_zero_total_is_a_no_match(): void
    {
        self::assertTrue($this->isNoMatch(0, [
            TestCommandOptions::EXCLUDE => ['slow'],
        ]));
    }

    public function test_only_tests_selector_zero_total_is_a_no_match(): void
    {
        self::assertTrue($this->isNoMatch(0, [
            TestCommandOptions::ONLY_TESTS => ['some-ns/some-test'],
        ]));
    }

    public function test_filter_selector_nonzero_total_is_not_a_no_match(): void
    {
        self::assertFalse($this->isNoMatch(3, [
            TestCommandOptions::FILTERS => ['my-test'],
        ]));
    }

    public function test_explicit_paths_zero_total_is_a_no_match(): void
    {
        self::assertTrue($this->isNoMatch(0, [], ['some/file.phel']));
    }

    public function test_explicit_paths_nonzero_total_is_not_a_no_match(): void
    {
        self::assertFalse($this->isNoMatch(2, [], ['some/file.phel']));
    }

    public function test_list_only_zero_total_is_not_a_no_match(): void
    {
        self::assertFalse($this->isNoMatch(0, [
            TestCommandOptions::LIST_ONLY => true,
            TestCommandOptions::FILTERS => ['my-test'],
        ], ['some/file.phel']));
    }

    public function test_structural_options_only_zero_total_is_not_a_no_match(): void
    {
        self::assertFalse($this->isNoMatch(0, [
            TestCommandOptions::TESTDOX => true,
            TestCommandOptions::FAIL_FAST => true,
            TestCommandOptions::REPORTERS => ['dot'],
        ]));
    }

    // --- exit-code constants are self::FAILURE on no-match ---

    public function test_failure_constant_is_non_zero(): void
    {
        self::assertSame(Command::FAILURE, 1);
    }

    // --- helpers ---

    /**
     * @param array<string, mixed> $options
     * @param list<string>         $paths
     */
    private function isNoMatch(int $total, array $options, array $paths = []): bool
    {
        $method = new ReflectionMethod(TestCommand::class, 'isNoMatchWithSelectors');

        return (bool) $method->invoke(new TestCommand(), $total, $options, $paths);
    }
}
