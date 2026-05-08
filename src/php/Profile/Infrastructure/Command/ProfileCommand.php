<?php

declare(strict_types=1);

namespace Phel\Profile\Infrastructure\Command;

use Gacela\Framework\ServiceResolver\ServiceMap;
use Gacela\Framework\ServiceResolverAwareTrait;
use Phel\Compiler\Application\Munge;
use Phel\Compiler\Domain\Exceptions\CompilerException;
use Phel\Lang\Registry;
use Phel\Phel;
use Phel\Profile\ProfileConfig;
use Phel\Profile\ProfileFacade;
use Phel\Profile\ProfileFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

use function file_exists;
use function file_put_contents;
use function implode;
use function in_array;
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
            ->addOption(self::OPT_FORMAT, null, InputOption::VALUE_REQUIRED, 'Output format: table, json, both.', ProfileConfig::FORMAT_TABLE)
            ->addOption(self::OPT_OUTPUT, null, InputOption::VALUE_REQUIRED, 'Write JSON report to this file.')
            ->addOption(self::OPT_SORT, null, InputOption::VALUE_REQUIRED, 'Sort fns by: self, total, calls, avg.', ProfileConfig::SORT_SELF)
            ->addOption(self::OPT_NO_COMPILE_PHASES, null, InputOption::VALUE_NONE, 'Skip the compile-time phase report.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $path = $this->resolvePath($input, $output);
        if ($path === null) {
            return self::FAILURE;
        }

        $sort = $this->resolveSort($input, $output);
        if ($sort === null) {
            return self::FAILURE;
        }

        $format = $this->resolveFormat($input, $output);
        if ($format === null) {
            return self::FAILURE;
        }

        /** @var list<string>|string|null $rawArgv */
        $rawArgv = $input->getArgument(self::ARG_ARGV);
        $userArgv = is_array($rawArgv) ? $rawArgv : [];
        Phel::setupRuntimeArgs($path, $userArgv);

        $session = $this->getFacade()->startSession();
        Registry::$profilerHook = $session;

        try {
            $runFacade = $this->getFactory()->getRunFacade();
            if (file_exists($path)) {
                $runFacade->runFile($path);
            } else {
                $runFacade->runNamespace(Munge::canonicalNs($path));
            }
        } catch (CompilerException $e) {
            Registry::$profilerHook = null;
            $this->getFactory()->getRunFacade()->writeLocatedException($output, $e);

            return self::FAILURE;
        } catch (Throwable $e) {
            Registry::$profilerHook = null;
            $this->getFactory()->getRunFacade()->writeStackTrace($output, $e);

            return self::FAILURE;
        } finally {
            Registry::$profilerHook = null;
        }

        $report = $session->stop();

        $top = (int) $input->getOption(self::OPT_TOP);
        if ($top <= 0) {
            $top = ProfileConfig::DEFAULT_TOP;
        }

        $includeCompilePhases = !$input->getOption(self::OPT_NO_COMPILE_PHASES);

        if (in_array($format, [ProfileConfig::FORMAT_TABLE, ProfileConfig::FORMAT_BOTH], true)) {
            $output->write($this->getFacade()->renderTable($report, $top, $sort, $includeCompilePhases));
        }

        $outputFile = $input->getOption(self::OPT_OUTPUT);
        $writeJsonToFile = is_string($outputFile) && $outputFile !== '';

        if ($writeJsonToFile || in_array($format, [ProfileConfig::FORMAT_JSON, ProfileConfig::FORMAT_BOTH], true)) {
            $json = $this->getFacade()->renderJson($report);
            if ($writeJsonToFile) {
                file_put_contents($outputFile, $json);
                $output->writeln(sprintf('<info>JSON report written to %s</info>', $outputFile));
            } elseif ($format === ProfileConfig::FORMAT_JSON) {
                $output->writeln($json);
            } else {
                $output->writeln('');
                $output->writeln($json);
            }
        }

        return self::SUCCESS;
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

    private function resolveSort(InputInterface $input, OutputInterface $output): ?string
    {
        $sort = (string) $input->getOption(self::OPT_SORT);
        $valid = [ProfileConfig::SORT_SELF, ProfileConfig::SORT_TOTAL, ProfileConfig::SORT_CALLS, ProfileConfig::SORT_AVG];
        if (!in_array($sort, $valid, true)) {
            $output->writeln(sprintf('<error>Unknown sort: %s. Allowed: %s.</error>', $sort, implode(', ', $valid)));

            return null;
        }

        return $sort;
    }

    private function resolveFormat(InputInterface $input, OutputInterface $output): ?string
    {
        $format = (string) $input->getOption(self::OPT_FORMAT);
        $valid = [ProfileConfig::FORMAT_TABLE, ProfileConfig::FORMAT_JSON, ProfileConfig::FORMAT_BOTH];
        if (!in_array($format, $valid, true)) {
            $output->writeln(sprintf('<error>Unknown format: %s. Allowed: %s.</error>', $format, implode(', ', $valid)));

            return null;
        }

        return $format;
    }
}
