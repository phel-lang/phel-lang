<?php

declare(strict_types=1);

namespace Phel\Run\Infrastructure\Command;

use Phel\Shared\Exceptions\ErrorCodeCatalog;
use Phel\Shared\Exceptions\ErrorCodeExplanation;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function explode;
use function is_string;
use function sprintf;
use function trim;

/**
 * Prints what one `[PHELxxx]` code off a terminal means.
 *
 * The prose comes from {@see ErrorCodeCatalog}, the same source the pages under
 * `docs/errors/` are generated from, so this command only renders.
 *
 * @internal
 */
final class ExplainCommand extends Command
{
    public const string COMMAND_NAME = 'explain';

    public const string DESCRIPTION = 'Explain a Phel error code, for example PHEL001';

    private const string ARG_CODE = 'code';

    private const string INDENT = '  ';

    protected function configure(): void
    {
        $this->setName(self::COMMAND_NAME)
            ->setDescription(self::DESCRIPTION)
            ->setHelp(<<<'HELP'
Prints the meaning, a minimal example and the fix for one error code.

The code is case-insensitive and the PHEL prefix is optional.

<info>Examples:</info>
  <comment>phel explain PHEL001</comment>   Explain one code
  <comment>phel explain 1</comment>         The same code, typed short
  <comment>phel explain</comment>           List every code
HELP)
            ->addArgument(
                self::ARG_CODE,
                InputArgument::OPTIONAL,
                'The error code to explain, for example PHEL001. Omit it to list every code.',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $code = $input->getArgument(self::ARG_CODE);
        $code = is_string($code) ? trim($code) : '';

        if ($code === '') {
            $this->writeCodeList($output);

            return self::SUCCESS;
        }

        $explanation = ErrorCodeCatalog::find($code);
        if (!$explanation instanceof ErrorCodeExplanation) {
            return $this->writeUnknownCode($output, $code);
        }

        $this->writeExplanation($output, $explanation);

        return self::SUCCESS;
    }

    private function writeExplanation(OutputInterface $output, ErrorCodeExplanation $explanation): void
    {
        $output->writeln(sprintf(
            '<info>%s</info> <comment>[%s]</comment>',
            $explanation->title,
            $explanation->code->value,
        ));
        $output->writeln('');
        $output->writeln($explanation->summary);
        $output->writeln('');
        $output->writeln('<comment>Example:</comment>');
        $this->writeIndented($output, $explanation->example);
        $output->writeln('');
        $output->writeln('<comment>Fix:</comment>');
        $this->writeIndented($output, $explanation->fix);
    }

    private function writeCodeList(OutputInterface $output): void
    {
        $output->writeln('<comment>Error codes:</comment>');

        foreach (ErrorCodeCatalog::all() as $explanation) {
            $output->writeln(sprintf(
                ' - <info>%s</info>  %s',
                $explanation->code->value,
                $explanation->title,
            ));
        }

        $output->writeln('');
        $output->writeln('Run <comment>phel explain PHEL001</comment> to read one entry.');
    }

    private function writeUnknownCode(OutputInterface $output, string $code): int
    {
        $output->writeln(sprintf('<error>Unknown error code "%s".</error>', $code));
        $output->writeln('Run <comment>phel explain</comment> without an argument to list every code.');

        return self::FAILURE;
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
