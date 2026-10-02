<?php

declare(strict_types=1);

namespace PhelTest\Unit\Run\Infrastructure\Command;

use Phel\Lint\Domain\LintRuleCatalog;
use Phel\Lint\LintFacade;
use Phel\Run\Infrastructure\Command\ExplainCommand;
use Phel\Shared\Exceptions\ErrorCode;
use Phel\Shared\Exceptions\ErrorCodeCatalog;
use Phel\Shared\LintRuleCodes;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

use function count;
use function json_decode;

use const JSON_THROW_ON_ERROR;

/**
 * The catalog is the data source, so these assertions read the entry they
 * expect back out of it instead of repeating its prose. PHEL001 is the one
 * code that is guaranteed to be there.
 */
final class ExplainCommandTest extends TestCase
{
    public function test_a_valid_code_prints_its_title_summary_example_and_fix(): void
    {
        $expected = ErrorCodeCatalog::explain(ErrorCode::UNDEFINED_SYMBOL);
        $tester = new CommandTester(new ExplainCommand(new LintFacade()));

        $exitCode = $tester->execute(['code' => 'PHEL001']);

        $display = $tester->getDisplay();
        self::assertSame(0, $exitCode);
        self::assertStringContainsString('[PHEL001]', $display);
        self::assertStringContainsString($expected->title, $display);
        self::assertStringContainsString($expected->summary, $display);
        self::assertStringContainsString('Example:', $display);
        self::assertStringContainsString('  ' . $expected->example, $display);
        self::assertStringContainsString('Fix:', $display);
        self::assertStringContainsString('  ' . $expected->fix, $display);
    }

    public function test_the_phel_prefix_is_optional(): void
    {
        $tester = new CommandTester(new ExplainCommand(new LintFacade()));

        $exitCode = $tester->execute(['code' => '1']);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('[PHEL001]', $tester->getDisplay());
    }

    public function test_the_code_is_case_insensitive_and_leading_zeroes_are_optional(): void
    {
        foreach (['phel001', 'Phel001', '001'] as $input) {
            $tester = new CommandTester(new ExplainCommand(new LintFacade()));

            $exitCode = $tester->execute(['code' => $input]);

            self::assertSame(0, $exitCode, $input . ': expected to resolve');
            self::assertStringContainsString('[PHEL001]', $tester->getDisplay(), $input . ': wrong entry');
        }
    }

    public function test_an_unknown_code_fails_and_names_the_input(): void
    {
        $tester = new CommandTester(new ExplainCommand(new LintFacade()));

        $exitCode = $tester->execute(['code' => 'PHEL999']);

        $display = $tester->getDisplay();
        self::assertSame(1, $exitCode);
        self::assertStringContainsString('Unknown error code "PHEL999".', $display);
        self::assertStringContainsString('phel explain', $display);
        self::assertStringContainsString('list every code', $display);
    }

    public function test_input_that_is_not_a_code_at_all_fails_the_same_way(): void
    {
        $tester = new CommandTester(new ExplainCommand(new LintFacade()));

        $exitCode = $tester->execute(['code' => 'nonsense']);

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('Unknown error code "nonsense".', $tester->getDisplay());
    }

    public function test_no_argument_lists_every_code_with_its_title(): void
    {
        $tester = new CommandTester(new ExplainCommand(new LintFacade()));

        $exitCode = $tester->execute([]);

        $display = $tester->getDisplay();
        self::assertSame(0, $exitCode);

        foreach (ErrorCodeCatalog::all() as $explanation) {
            self::assertStringContainsString($explanation->code->value, $display);
            self::assertStringContainsString($explanation->title, $display);
        }
    }

    public function test_the_listing_prints_one_line_per_code(): void
    {
        $tester = new CommandTester(new ExplainCommand(new LintFacade()));
        $tester->execute([]);

        $lines = preg_split('/\R/', trim($tester->getDisplay())) ?: [];
        $entryLines = array_filter($lines, static fn(string $line): bool => str_starts_with($line, ' - '));

        self::assertCount(count(ErrorCodeCatalog::all()) + count(LintRuleCatalog::all()), $entryLines);
    }

    public function test_the_command_is_named_explain_and_takes_an_optional_code(): void
    {
        $command = new ExplainCommand(new LintFacade());

        self::assertSame('explain', $command->getName());
        self::assertSame(ExplainCommand::DESCRIPTION, $command->getDescription());
        self::assertFalse($command->getDefinition()->getArgument('code')->isRequired());
    }

    public function test_a_lint_rule_code_prints_its_entry(): void
    {
        $expected = LintRuleCatalog::find(LintRuleCodes::UNUSED_REQUIRE);
        self::assertNotNull($expected);
        $tester = new CommandTester(new ExplainCommand(new LintFacade()));

        $exitCode = $tester->execute(['code' => 'phel/unused-require']);

        $display = $tester->getDisplay();
        self::assertSame(0, $exitCode);
        self::assertStringContainsString('[phel/unused-require]', $display);
        self::assertStringContainsString($expected->summary, $display);
        self::assertStringContainsString('  ' . $expected->fix, $display);
    }

    public function test_json_format_prints_one_entry(): void
    {
        $expected = LintRuleCatalog::find(LintRuleCodes::UNUSED_REQUIRE);
        self::assertNotNull($expected);
        $tester = new CommandTester(new ExplainCommand(new LintFacade()));

        $exitCode = $tester->execute(['code' => 'phel/unused-require', '--format' => 'json']);

        self::assertSame(0, $exitCode);
        self::assertSame([
            'code' => 'phel/unused-require',
            'title' => $expected->title,
            'summary' => $expected->summary,
            'example' => $expected->example,
            'fix' => $expected->fix,
        ], json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR));
    }

    public function test_json_format_lists_every_code_and_rule(): void
    {
        $tester = new CommandTester(new ExplainCommand(new LintFacade()));

        $tester->execute(['--format' => 'json']);

        $listing = json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($listing);
        self::assertCount(count(ErrorCodeCatalog::all()) + count(LintRuleCatalog::all()), $listing);
        self::assertSame(['code' => 'PHEL001', 'title' => ErrorCodeCatalog::explain(ErrorCode::UNDEFINED_SYMBOL)->title], $listing[0]);
    }

    public function test_an_unknown_format_fails(): void
    {
        $tester = new CommandTester(new ExplainCommand(new LintFacade()));

        self::assertSame(1, $tester->execute(['code' => 'PHEL001', '--format' => 'xml']));
    }

    public function test_without_the_lint_facade_only_error_codes_are_known(): void
    {
        $tester = new CommandTester(new ExplainCommand());

        self::assertSame(1, $tester->execute(['code' => 'phel/unused-require']));
    }
}
