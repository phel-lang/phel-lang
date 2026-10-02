<?php

declare(strict_types=1);

namespace Phel\Run\Infrastructure\Command;

use Phel\Shared\Exceptions\ErrorCodeCatalog;
use Phel\Shared\Exceptions\ErrorCodeExplanation;
use Phel\Shared\InstallDocsPath;
use Phel\Shared\LintRuleCatalog;
use Phel\Shared\LintRuleExplanation;
use Phel\Shared\ScalarCoercion;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function explode;
use function is_string;
use function json_encode;
use function sprintf;
use function trim;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

/**
 * Prints what one `[PHELxxx]` code or lint rule code off a terminal means.
 *
 * The prose comes from {@see ErrorCodeCatalog}, the same source the pages under
 * `docs/errors/` are generated from, and {@see LintRuleCatalog}, so this command
 * only renders.
 *
 * @internal
 */
final class ExplainCommand extends Command
{
    public const string COMMAND_NAME = 'explain';

    public const string DESCRIPTION = 'Explain a Phel error code or lint rule, for example PHEL001';

    private const string ARG_CODE = 'code';

    private const string OPT_FORMAT = 'format';

    private const string FORMAT_TEXT = 'text';

    private const string FORMAT_JSON = 'json';

    private const string INDENT = '  ';

    protected function configure(): void
    {
        $this->setName(self::COMMAND_NAME)
            ->setDescription(self::DESCRIPTION)
            ->setHelp(<<<'HELP'
Prints the meaning, a minimal example and the fix for one error code or lint rule.

An error code is case-insensitive and the PHEL prefix is optional. A lint rule
is the code phel lint prints, with or without the phel/ prefix.

<info>Examples:</info>
  <comment>phel explain PHEL001</comment>                       Explain one code
  <comment>phel explain 1</comment>                             The same code, typed short
  <comment>phel explain phel/unused-require</comment>           Explain one lint rule
  <comment>phel explain PHEL001 --format=json</comment>         The same entry as JSON
  <comment>phel explain</comment>                               List every code and rule
HELP)
            ->addArgument(
                self::ARG_CODE,
                InputArgument::OPTIONAL,
                'The error code or lint rule to explain, for example PHEL001. Omit it to list every one.',
            )
            ->addOption(
                self::OPT_FORMAT,
                null,
                InputOption::VALUE_REQUIRED,
                'Output format: text or json',
                self::FORMAT_TEXT,
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $format = $input->getOption(self::OPT_FORMAT);
        if ($format !== self::FORMAT_TEXT && $format !== self::FORMAT_JSON) {
            $output->writeln(sprintf('<error>Unknown format "%s". Use text or json.</error>', ScalarCoercion::toString($format)));

            return self::FAILURE;
        }

        $json = $format === self::FORMAT_JSON;
        $code = $input->getArgument(self::ARG_CODE);
        $code = is_string($code) ? trim($code) : '';

        if ($code === '') {
            $json ? $this->writeJson($output, $this->listing()) : $this->writeCodeList($output);

            return self::SUCCESS;
        }

        $entry = $this->find($code);
        if ($entry === null) {
            return $this->writeUnknownCode($output, $code, $json);
        }

        $json ? $this->writeJson($output, $entry) : $this->writeExplanation($output, $entry);

        return self::SUCCESS;
    }

    /**
     * @return array{code: string, title: string, summary: string, example: string, fix: string}|null
     */
    private function find(string $code): ?array
    {
        $explanation = ErrorCodeCatalog::find($code);
        if ($explanation instanceof ErrorCodeExplanation) {
            return $this->entry($explanation->code->value, $explanation->title, $explanation->summary, $explanation->example, $explanation->fix);
        }

        $rule = LintRuleCatalog::find($code);
        if ($rule instanceof LintRuleExplanation) {
            return $this->entry($rule->code, $rule->title, $rule->summary, $rule->example, $rule->fix);
        }

        return null;
    }

    /**
     * @return array{code: string, title: string, summary: string, example: string, fix: string}
     */
    private function entry(string $code, string $title, string $summary, string $example, string $fix): array
    {
        return [
            'code' => $code,
            'title' => $title,
            'summary' => $summary,
            'example' => $example,
            'fix' => InstallDocsPath::rewrite($fix),
        ];
    }

    /**
     * @return list<array{code: string, title: string}>
     */
    private function listing(): array
    {
        $listing = [];
        foreach (ErrorCodeCatalog::all() as $explanation) {
            $listing[] = ['code' => $explanation->code->value, 'title' => $explanation->title];
        }

        foreach (LintRuleCatalog::all() as $rule) {
            $listing[] = ['code' => $rule->code, 'title' => $rule->title];
        }

        return $listing;
    }

    /**
     * @param array{code: string, title: string, summary: string, example: string, fix: string} $entry
     */
    private function writeExplanation(OutputInterface $output, array $entry): void
    {
        $output->writeln(sprintf('<info>%s</info> <comment>[%s]</comment>', $entry['title'], $entry['code']));
        $output->writeln('');
        $output->writeln($entry['summary']);
        if ($entry['example'] !== '') {
            $output->writeln('');
            $output->writeln('<comment>Example:</comment>');
            $this->writeIndented($output, $entry['example']);
        }

        $output->writeln('');
        $output->writeln('<comment>Fix:</comment>');
        $this->writeIndented($output, $entry['fix']);
    }

    private function writeCodeList(OutputInterface $output): void
    {
        $output->writeln('<comment>Error codes:</comment>');
        foreach (ErrorCodeCatalog::all() as $explanation) {
            $output->writeln(sprintf(' - <info>%s</info>  %s', $explanation->code->value, $explanation->title));
        }

        $output->writeln('');
        $output->writeln('<comment>Lint rules:</comment>');
        foreach (LintRuleCatalog::all() as $rule) {
            $output->writeln(sprintf(' - <info>%s</info>  %s', $rule->code, $rule->title));
        }

        $output->writeln('');
        $output->writeln('Run <comment>phel explain PHEL001</comment> to read one entry.');
    }

    private function writeUnknownCode(OutputInterface $output, string $code, bool $json): int
    {
        if ($json) {
            $this->writeJson($output, ['error' => sprintf('Unknown error code "%s".', $code)]);

            return self::FAILURE;
        }

        $output->writeln(sprintf('<error>Unknown error code "%s".</error>', $code));
        $output->writeln('Run <comment>phel explain</comment> without an argument to list every code.');

        return self::FAILURE;
    }

    /**
     * @param array<array-key, mixed> $payload
     */
    private function writeJson(OutputInterface $output, array $payload): void
    {
        $output->writeln(
            json_encode($payload, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            OutputInterface::OUTPUT_RAW,
        );
    }

    /**
     * Indents every line, so a multi-line example keeps its shape instead of
     * only its first line lining up under the heading.
     */
    private function writeIndented(OutputInterface $output, string $text): void
    {
        foreach (explode("\n", $text) as $line) {
            $output->writeln(self::INDENT . $line);
        }
    }
}
