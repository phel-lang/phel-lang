<?php

declare(strict_types=1);

namespace PhelTest\Unit\Run\Infrastructure\Command;

use Phel\Run\Infrastructure\Command\ExplainCommand;
use Phel\Shared\Exceptions\ErrorCode;
use Phel\Shared\Exceptions\ErrorCodeCatalog;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

use function count;

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
        $tester = new CommandTester(new ExplainCommand());

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
        $tester = new CommandTester(new ExplainCommand());

        $exitCode = $tester->execute(['code' => '1']);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('[PHEL001]', $tester->getDisplay());
    }

    public function test_the_code_is_case_insensitive_and_leading_zeroes_are_optional(): void
    {
        foreach (['phel001', 'Phel001', '001'] as $input) {
            $tester = new CommandTester(new ExplainCommand());

            $exitCode = $tester->execute(['code' => $input]);

            self::assertSame(0, $exitCode, $input . ': expected to resolve');
            self::assertStringContainsString('[PHEL001]', $tester->getDisplay(), $input . ': wrong entry');
        }
    }

    public function test_an_unknown_code_fails_and_names_the_input(): void
    {
        $tester = new CommandTester(new ExplainCommand());

        $exitCode = $tester->execute(['code' => 'PHEL999']);

        $display = $tester->getDisplay();
        self::assertSame(1, $exitCode);
        self::assertStringContainsString('Unknown error code "PHEL999".', $display);
        self::assertStringContainsString('phel explain', $display);
        self::assertStringContainsString('list every code', $display);
    }

    public function test_input_that_is_not_a_code_at_all_fails_the_same_way(): void
    {
        $tester = new CommandTester(new ExplainCommand());

        $exitCode = $tester->execute(['code' => 'nonsense']);

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('Unknown error code "nonsense".', $tester->getDisplay());
    }

    public function test_no_argument_lists_every_code_with_its_title(): void
    {
        $tester = new CommandTester(new ExplainCommand());

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
        $tester = new CommandTester(new ExplainCommand());
        $tester->execute([]);

        $lines = preg_split('/\R/', trim($tester->getDisplay())) ?: [];
        $entryLines = array_filter($lines, static fn(string $line): bool => str_starts_with($line, ' - '));

        self::assertCount(count(ErrorCodeCatalog::all()), $entryLines);
    }

    public function test_the_command_is_named_explain_and_takes_an_optional_code(): void
    {
        $command = new ExplainCommand();

        self::assertSame('explain', $command->getName());
        self::assertSame(ExplainCommand::DESCRIPTION, $command->getDescription());
        self::assertFalse($command->getDefinition()->getArgument('code')->isRequired());
    }
}
