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
use Phel\Balance\Domain\RepairPlan;
use Phel\Balance\Domain\RepairStrategy;
use Phel\Shared\ExistingPaths;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function count;
use function is_string;
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

    private const string OPT_REPAIR = 'repair';

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

The default repair strategy only appends missing closers. With `--repair=boundary`,
missing closers can be inserted before a detected new top-level form. With
`--repair=delete-unexpected`, one validated surplus closer can be deleted. With
`--repair=search`, a bounded three-edit search reconciles mismatched, surplus and
missing closers, keeping a repair only when it is the unique cheapest candidate
that re-lexes, parses and re-scans balanced.
Anything ambiguous is reported and left untouched: a mismatched closer with no
disambiguating context such as `(foo]`, an unterminated string, a file that will
not lex, a trailing reader prefix such as `#_`, and a missing closer with a new
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
                'Repair the files using the selected strategy.',
            )
            ->addOption(
                self::OPT_REPAIR,
                null,
                InputOption::VALUE_REQUIRED,
                'Repair strategy: append, boundary, delete-unexpected or search.',
                RepairStrategy::Append->value,
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var list<string> $requestedPaths */
        $requestedPaths = $input->getArgument(self::ARG_PATHS);
        $fix = (bool) $input->getOption(self::OPT_FIX);
        $repairOption = $input->getOption(self::OPT_REPAIR);
        $strategy = RepairStrategy::tryFrom(is_string($repairOption) ? $repairOption : '');

        if ($strategy === null || (!$fix && $strategy !== RepairStrategy::Append)) {
            $output->writeln('<error>--repair requires --fix and must be append, boundary, delete-unexpected or search.</error>');

            return self::EXIT_INVOCATION_ERROR;
        }

        $paths = ExistingPaths::filter($requestedPaths === [] ? $this->defaultPaths() : $requestedPaths);

        if ($paths === []) {
            $output->writeln('<error>No readable files or directories to scan.</error>');

            return self::EXIT_INVOCATION_ERROR;
        }

        try {
            $result = $this->getFacade()->balance($paths, $fix, $strategy);
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
            if ($report instanceof BalanceReport) {
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

            if ($output->isVerbose()) {
                $this->reportPlan($output, $outcome, $heading);
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

            if ($output->isVerbose()) {
                $this->reportPlan($output, $outcome, 'Cannot repair automatically');
            }
        }
    }

    private function reportPlan(OutputInterface $output, FileOutcome $outcome, string $heading): void
    {
        $plan = $outcome->plan;
        if (!$plan instanceof RepairPlan) {
            return;
        }

        $output->writeln('  strategy: search');
        $candidates = $plan->candidates;
        $output->writeln(sprintf('  candidates: %d', count($candidates)));
        foreach ($candidates as $i => $candidate) {
            $output->writeln(sprintf('  candidate %d: cost %d', $i + 1, $candidate->cost()));
            $output->writeln($candidate->describe());
            $output->writeln(sprintf('    parser validation: %s', $candidate->parserValid ? 'passed' : 'failed'));
        }

        if ($plan->refusalReason !== null) {
            $output->writeln(sprintf('  refused: %s', $plan->refusalReason));
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
