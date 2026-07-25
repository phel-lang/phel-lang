<?php

declare(strict_types=1);

namespace PhelTest\Integration\Api;

use Phel;
use Phel\Api\Infrastructure\Command\DocCommand;
use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;
use Phel\Lang\Symbol;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandCompletionTester;
use Symfony\Component\Console\Tester\CommandTester;

final class DocCommandTest extends TestCase
{
    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_search_argument_completes_function_names(): void
    {
        $this->bootstrap();

        $tester = new CommandCompletionTester(new DocCommand());
        $suggestions = $tester->complete(['map']);

        self::assertContains('core/map', $suggestions);
        self::assertContains('core/map-indexed', $suggestions);
        self::assertNotContains('core/reduce', $suggestions, 'Suggestions must be filtered by the typed value');
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_ns_option_completes_namespaces(): void
    {
        $this->bootstrap();

        $tester = new CommandCompletionTester(new DocCommand());
        $suggestions = $tester->complete(['--ns', '']);

        self::assertContains('core', $suggestions);
        // Namespaces must be unique.
        self::assertSame(array_values(array_unique($suggestions)), $suggestions);
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_format_option_completes_available_formats(): void
    {
        $this->bootstrap();

        $tester = new CommandCompletionTester(new DocCommand());
        $suggestions = $tester->complete(['--format', '']);

        self::assertSame(['table', 'json'], $suggestions);
    }

    /**
     * A search nothing is similar enough to used to render a header-only table,
     * which reads as "something went wrong with the table" rather than "no
     * match". The exit code stays 0: a search finding nothing is an answer, not
     * an error.
     */
    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_a_search_without_matches_says_so_and_still_succeeds(): void
    {
        $this->bootstrap();

        $tester = new CommandTester(new DocCommand());
        $exitCode = $tester->execute(['search' => 'zzzzqqqqxxxxwwww']);
        $display = $tester->getDisplay();

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('No function matches "zzzzqqqqxxxxwwww".', $display);
        self::assertStringNotContainsString('| function | signature | description |', $display);
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_a_search_with_matches_still_prints_the_table(): void
    {
        $this->bootstrap();

        $tester = new CommandTester(new DocCommand());
        $exitCode = $tester->execute(['search' => 'map-indexed']);
        $display = $tester->getDisplay();

        self::assertSame(Command::SUCCESS, $exitCode);
        // The table wraps long names across lines, so match on the header plus
        // the wrapped halves of `core/map-indexed` rather than the whole name.
        self::assertStringContainsString('| function', $display);
        self::assertStringContainsString('core/map-ind', $display);
        self::assertStringNotContainsString('No function matches', $display);
    }

    /**
     * `[]` is already an unambiguous machine-readable "no matches", so the json
     * format must not grow the human-facing sentence.
     */
    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_the_json_format_stays_an_empty_array_without_matches(): void
    {
        $this->bootstrap();

        $tester = new CommandTester(new DocCommand());
        $exitCode = $tester->execute(['search' => 'zzzzqqqqxxxxwwww', '--format' => 'json']);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertSame('[]', trim($tester->getDisplay()));
    }

    private function bootstrap(): void
    {
        Phel::bootstrap(__DIR__);
        Phel::clear();
        Symbol::resetGen();
        GlobalEnvironmentSingleton::initializeNew();
    }
}
