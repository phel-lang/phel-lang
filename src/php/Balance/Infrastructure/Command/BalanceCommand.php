<?php

declare(strict_types=1);

namespace Phel\Balance\Infrastructure\Command;

use Gacela\Framework\ServiceResolver\ServiceMap;
use Gacela\Framework\ServiceResolverAwareTrait;
use Phel\Balance\BalanceConfig;
use Phel\Balance\BalanceFacade;
use Phel\Balance\BalanceFactory;
use Phel\Balance\Domain\BalanceOutcome;
use Phel\Balance\Domain\BalanceReport;
use Phel\Balance\Domain\Exception\BalanceSourceException;
use Phel\Balance\Domain\FileOutcome;
use Phel\Shared\ExistingPaths;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function count;
use function sprintf;

/**
 * `./bin/phel balance <paths>` — reports, and with `--fix` repairs, unbalanced
 * `()`, `[]` and `{}` in Phel sources. Exits with:
 *   0 — every scanned file is balanced
 *   1 — at least one file was unbalanced (whether or not `--fix` repaired it)
 *   2 — invocation error (no readable paths, unwalkable directory).
 *
 * Detection only by default. An agent post-write hook that silently guesses
 * wrong is worse than the compile error it replaces, so writing is opt-in.
 *
 * @method BalanceFacade  getFacade()
 * @method BalanceFactory getFactory()
 * @method BalanceConfig  getConfig()
 *
 * @internal
 */
#[ServiceMap(method: 'getFacade', className: BalanceFacade::class)]
#[ServiceMap(method: 'getFactory', className: BalanceFactory::class)]
#[ServiceMap(method: 'getConfig', className: BalanceConfig::class)]
final class BalanceCommand extends Command
{
    use ServiceResolverAwareTrait;

    public const int EXIT_INVOCATION_ERROR = 2;

    private const string COMMAND_NAME = 'balance';

    private const string ARG_PATHS = 'paths';

    private const string OPT_FIX = 'fix';

    public function __construct()
    {
        parent::__construct(self::COMMAND_NAME);
    }

    protected function configure(): void
    {
        $this->setDescription('Report unbalanced parentheses, brackets and braces in Phel files; repair them with --fix.')
            ->setHelp(<<<'HELP'
Scans on the lexer's token stream, so a `(` inside a string, a `;` comment,
a `#"regex"` or a `\(` character literal is not counted.

Only missing closers are repaired, and only by appending them. Anything with
more than one plausible fix is reported and left untouched: a surplus or
mismatched closer (`(foo]`), an unterminated string, a file that will not lex,
a trailing reader prefix such as `#_`, and a missing closer with a new
top-level form after it, where appending at the end would nest the rest of the
file inside the open form.

<info>Examples:</info>
  <comment>phel balance src</comment>                Report imbalances, change nothing
  <comment>phel balance src/main.phel --fix</comment>   Append the missing closers
HELP)
            ->addArgument(
                self::ARG_PATHS,
                InputArgument::IS_ARRAY,
                'Files or directories to scan (defaults to the configured source dirs).',
                [],
            )
            ->addOption(
                self::OPT_FIX,
                null,
                InputOption::VALUE_NONE,
                'Append the missing closing delimiters to the files that can take them.',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var list<string> $requestedPaths */
        $requestedPaths = $input->getArgument(self::ARG_PATHS);
        $fix = (bool) $input->getOption(self::OPT_FIX);

        $paths = ExistingPaths::filter($requestedPaths === [] ? $this->defaultPaths() : $requestedPaths);

        if ($paths === []) {
            $output->writeln('<error>No readable files or directories to scan.</error>');

            return self::EXIT_INVOCATION_ERROR;
        }

        try {
            $result = $this->getFacade()->balance($paths, $fix);
        } catch (BalanceSourceException $balanceSourceException) {
            $output->writeln(sprintf('<error>%s</error>', $balanceSourceException->getMessage()));

            return self::EXIT_INVOCATION_ERROR;
        }

        $repaired = $result->withOutcome(BalanceOutcome::Repaired);
        $needsRepair = $result->withOutcome(BalanceOutcome::NeedsRepair);
        $unrepairable = $result->withOutcome(BalanceOutcome::Unrepairable);

        $this->report($output, $repaired, 'Repaired');
        $this->report($output, $needsRepair, 'Unbalanced');
        $this->reportUnrepairable($output, $unrepairable);

        if ($repaired === [] && $needsRepair === [] && $unrepairable === []) {
            $output->writeln(sprintf('%d file(s) scanned, all balanced.', $result->scannedCount()));

            return self::SUCCESS;
        }

        if ($needsRepair !== []) {
            $output->writeln('Run again with --fix to append the missing delimiters.');
        }

        // A `--fix` run that repaired everything it was asked to leaves nothing
        // broken, so it exits 0. Exiting non-zero there would fail the agent
        // post-write hook this command exists to serve.
        return $needsRepair === [] && $unrepairable === []
            ? self::SUCCESS
            : self::FAILURE;
    }

    /**
     * @param list<FileOutcome> $outcomes
     */
    private function report(OutputInterface $output, array $outcomes, string $heading): void
    {
        if ($outcomes === []) {
            return;
        }

        $output->writeln(sprintf('%s (%d):', $heading, count($outcomes)));

        foreach ($outcomes as $outcome) {
            $report = $outcome->report;
            if (!$report instanceof BalanceReport) {
                continue;
            }

            foreach ($report->unclosed as $open) {
                $output->writeln(sprintf(
                    "  %s:%d:%d: unclosed '%s', needs '%s'",
                    $outcome->path,
                    $open->line,
                    $open->column,
                    $open->openerText,
                    $open->closerText,
                ));
            }
        }
    }

    /**
     * @param list<FileOutcome> $outcomes
     */
    private function reportUnrepairable(OutputInterface $output, array $outcomes): void
    {
        if ($outcomes === []) {
            return;
        }

        $output->writeln(sprintf('Cannot repair automatically (%d):', count($outcomes)));

        foreach ($outcomes as $outcome) {
            $output->writeln(sprintf('  %s: %s', $outcome->path, $outcome->reason ?? 'unknown reason'));
        }
    }

    /**
     * @return list<string>
     */
    private function defaultPaths(): array
    {
        return $this->getFactory()
            ->getCommandFacade()
            ->getProjectSourceDirectories();
    }
}
