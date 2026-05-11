<?php

declare(strict_types=1);

namespace Phel\Profile\Infrastructure\Command;

use BackedEnum;
use Gacela\Framework\ServiceResolver\ServiceMap;
use Gacela\Framework\ServiceResolverAwareTrait;
use Phel\Compiler\Domain\Exceptions\CompilerException;
use Phel\Lang\Registry;
use Phel\Phel;
use Phel\Profile\Domain\ProfileReport;
use Phel\Profile\Domain\ReportFormat;
use Phel\Profile\Domain\SortOrder;
use Phel\Profile\ProfileConfig;
use Phel\Profile\ProfileFacade;
use Phel\Profile\ProfileFactory;
use Phel\Shared\Munge;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Throwable;

use function array_map;
use function file_exists;
use function file_put_contents;
use function implode;
use function is_array;
use function is_string;
use function sprintf;

#[ServiceMap(method: 'getFacade', className: ProfileFacade::class)]
#[ServiceMap(method: 'getFactory', className: ProfileFactory::class)]
final class ProfileCommand extends Command
{
    use ServiceResolverAwareTrait;

    private const string COMMAND_NAME = 'profile';

    private const string ARG_PATH = 'path';

    private const string ARG_ARGV = 'argv';

    private const string OPT_TOP = 'top';

    private const string OPT_FORMAT = 'format';

    private const string OPT_OUTPUT = 'output';

    private const string OPT_SORT = 'sort';

    private const string OPT_NO_COMPILE_PHASES = 'no-compile-phases';

    public function __construct()
    {
        parent::__construct(self::COMMAND_NAME);
    }

    protected function configure(): void
    {
        $this->setDescription('Profile a Phel script: per-fn call counts and timings, plus compile-time phase costs.')
            ->addArgument(self::ARG_PATH, InputArgument::OPTIONAL, 'File path or namespace to profile.')
            ->addArgument(self::ARG_ARGV, InputArgument::IS_ARRAY | InputArgument::OPTIONAL, 'Optional script arguments.', [])
            ->addOption(self::OPT_TOP, null, InputOption::VALUE_REQUIRED, 'Show top N fns in the table.', (string) ProfileConfig::DEFAULT_TOP)
            ->addOption(self::OPT_FORMAT, null, InputOption::VALUE_REQUIRED, 'Output format: table, json, both.', ReportFormat::Table->value)
            ->addOption(self::OPT_OUTPUT, null, InputOption::VALUE_REQUIRED, 'Write JSON report to this file.')
            ->addOption(self::OPT_SORT, null, InputOption::VALUE_REQUIRED, 'Sort fns by: self, total, calls, avg.', SortOrder::SelfTime->value)
            ->addOption(self::OPT_NO_COMPILE_PHASES, null, InputOption::VALUE_NONE, 'Skip the compile-time phase report.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $path = $this->resolvePath($input, $output);
        if ($path === null) {
            return self::FAILURE;
        }

        $sort = SortOrder::tryFrom((string) $input->getOption(self::OPT_SORT));
        if ($sort === null) {
            $this->writeUnknownOption($output, self::OPT_SORT, (string) $input->getOption(self::OPT_SORT), SortOrder::cases());

            return self::FAILURE;
        }

        $format = ReportFormat::tryFrom((string) $input->getOption(self::OPT_FORMAT));
        if ($format === null) {
            $this->writeUnknownOption($output, self::OPT_FORMAT, (string) $input->getOption(self::OPT_FORMAT), ReportFormat::cases());

            return self::FAILURE;
        }

        /** @var list<string>|string|null $rawArgv */
        $rawArgv = $input->getArgument(self::ARG_ARGV);
        Phel::setupRuntimeArgs($path, is_array($rawArgv) ? $rawArgv : []);

        $report = $this->runWithProfiler($path, $output);
        if (!$report instanceof ProfileReport) {
            return self::FAILURE;
        }

        $this->renderReport($report, $input, $output, $sort, $format);

        return self::SUCCESS;
    }

    private function runWithProfiler(string $path, OutputInterface $output): ?ProfileReport
    {
        $runFacade = $this->getFactory()->getRunFacade();
        $session = $this->getFacade()->startSession();
        Registry::$profilerHook = $session;

        try {
            if (file_exists($path)) {
                $runFacade->runFile($path);
            } else {
                $runFacade->runNamespace(Munge::canonicalNs($path));
            }
        } catch (CompilerException $e) {
            $runFacade->writeLocatedException($output, $e);

            return null;
        } catch (Throwable $e) {
            $runFacade->writeStackTrace($output, $e);

            return null;
        } finally {
            Registry::$profilerHook = null;
        }

        return $session->stop();
    }

    private function renderReport(
        ProfileReport $report,
        InputInterface $input,
        OutputInterface $output,
        SortOrder $sort,
        ReportFormat $format,
    ): void {
        $top = $this->resolveTop($input);
        $includeCompilePhases = !$input->getOption(self::OPT_NO_COMPILE_PHASES);

        if ($format->emitsTable()) {
            $output->write($this->getFacade()->renderTable($report, $top, $sort, $includeCompilePhases));
        }

        $outputFile = $input->getOption(self::OPT_OUTPUT);
        $writeToFile = is_string($outputFile) && $outputFile !== '';
        if (!$writeToFile && !$format->emitsJson()) {
            return;
        }

        $json = $this->getFacade()->renderJson($report);
        if ($writeToFile) {
            file_put_contents($outputFile, $json);
            $output->writeln(sprintf('<info>JSON report written to %s</info>', $outputFile));

            return;
        }

        if ($format === ReportFormat::Both) {
            $output->writeln('');
        }

        $output->writeln($json);
    }

    private function resolvePath(InputInterface $input, OutputInterface $output): ?string
    {
        /** @var string|null $path */
        $path = $input->getArgument(self::ARG_PATH);
        if ($path !== null && $path !== '') {
            return $path;
        }

        $detected = $this->getFactory()->getRunFacade()->autoDetectEntryPoint();
        if ($detected === null) {
            $output->writeln('<error>No entry point found. Pass a file path or namespace.</error>');

            return null;
        }

        return $detected;
    }

    private function resolveTop(InputInterface $input): int
    {
        $top = (int) $input->getOption(self::OPT_TOP);

        return $top > 0 ? $top : ProfileConfig::DEFAULT_TOP;
    }

    /**
     * @param list<BackedEnum> $cases
     */
    private function writeUnknownOption(OutputInterface $output, string $option, string $value, array $cases): void
    {
        $allowed = array_map(static fn(BackedEnum $c): string => (string) $c->value, $cases);

        $output->writeln(sprintf(
            '<error>Unknown %s: %s. Allowed: %s.</error>',
            $option,
            $value,
            implode(', ', $allowed),
        ));
    }
}
