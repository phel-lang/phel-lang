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

use function is_array;
use function json_decode;
use function sprintf;

use const JSON_THROW_ON_ERROR;

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
    public function test_a_search_without_an_exact_match_says_so_and_prints_the_table(): void
    {
        $this->bootstrap();

        $tester = new CommandTester(new DocCommand());
        $exitCode = $tester->execute(['search' => 'mapp']);
        $display = $tester->getDisplay();

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('No exact match for "mapp". Closest:', $display);
        self::assertStringContainsString('| function', $display);
        self::assertStringContainsString('| core/map ', $display);
        self::assertStringNotContainsString('No function matches', $display);
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_an_exact_name_prints_the_full_doc(): void
    {
        $this->bootstrap();

        $tester = new CommandTester(new DocCommand());
        $exitCode = $tester->execute(['search' => 'reduce-kv']);
        $display = $tester->getDisplay();

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringStartsWith("core/reduce-kv\n(reduce-kv f init coll)\n\n", $display);
        self::assertStringContainsString('Reduces an associative collection', $display);
        self::assertStringContainsString("Example:\n  (reduce-kv", $display);
        self::assertStringContainsString('See also: ', $display);
        self::assertStringNotContainsString('| function', $display);
        self::assertStringNotContainsString('core/reduced', $display);
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_a_namespaced_exact_name_prints_the_full_doc(): void
    {
        $this->bootstrap();

        $tester = new CommandTester(new DocCommand());
        $tester->execute(['search' => 'string/upper-case']);

        self::assertStringStartsWith("string/upper-case\n(upper-case s)\n", $tester->getDisplay());
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

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_the_json_format_names_the_namespace_to_require(): void
    {
        $this->bootstrap();

        $upperCase = $this->jsonEntry('string/upper-case');

        self::assertSame('string', $upperCase['namespace']);
        self::assertSame('phel.string', $upperCase['requireNs']);
        self::assertSame('(:require phel.string :refer [upper-case])', $upperCase['require']);
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_a_core_fn_needs_no_require(): void
    {
        $this->bootstrap();

        $map = $this->jsonEntry('core/map');

        self::assertSame('phel.core', $map['requireNs']);
        self::assertNull($map['require']);
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonEntry(string $name): array
    {
        $tester = new CommandTester(new DocCommand());
        $tester->execute(['search' => $name, '--format' => 'json']);

        $entries = json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($entries);
        foreach ($entries as $entry) {
            if (is_array($entry) && ($entry['name'] ?? null) === $name) {
                return $entry;
            }
        }

        self::fail(sprintf('No JSON entry named %s', $name));
    }

    private function bootstrap(): void
    {
        Phel::bootstrap(__DIR__);
        Phel::clear();
        Symbol::resetGen();
        GlobalEnvironmentSingleton::initializeNew();
    }
}
