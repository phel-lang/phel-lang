<?php

declare(strict_types=1);

namespace Phel\Run\Infrastructure\Command;

use Gacela\Framework\ServiceResolver\ServiceMap;
use Gacela\Framework\ServiceResolverAwareTrait;
use Phel\Compiler\Domain\Exceptions\CompilerException;
use Phel\Phel;
use Phel\Run\Infrastructure\Service\DebugLineTap;
use Phel\Run\RunFacade;
use SebastianBergmann\Timer\ResourceUsageFormatter;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

use function function_exists;
use function is_array;
use function is_string;
use function sprintf;

/**
 * @method RunFacade getFacade()
 */
#[ServiceMap(method: 'getFacade', className: RunFacade::class)]
final class RunCommand extends Command
{
    use ServiceResolverAwareTrait;

    protected function configure(): void
    {
        $this->setName('run')
            ->setDescription('Runs a script')
            ->addArgument(
                'path',
                InputArgument::OPTIONAL,
                'The file path or namespace to execute (auto-detects core.phel or main.phel if omitted)',
            )->addArgument(
                'argv',
                InputArgument::IS_ARRAY | InputArgument::OPTIONAL,
                'Optional arguments',
                [],
            )->addOption(
                'with-time',
                't',
                InputOption::VALUE_NONE,
                'With time awareness',
            )->addOption(
                'clear-opcache',
                null,
                InputOption::VALUE_NONE,
                'Clears OPCache before running',
            )->addOption(
                'debug',
                null,
                InputOption::VALUE_OPTIONAL,
                'Enable line-by-line debug tracing to ./phel-debug.log (optional: Phel file filter using --debug="core")',
                false,
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $debugOption = $input->getOption('debug');
        if ($debugOption !== false) {
            $phelFileFilter = is_string($debugOption) && $debugOption !== '' ? $debugOption : null;
            DebugLineTap::enable($phelFileFilter);

            if ($output->isVerbose()) {
                $output->writeln('<info>Debug tracing enabled. Logging to: ./phel-debug.log</info>');
                if ($phelFileFilter !== null) {
                    $output->writeln(sprintf('<info>  Filtering Phel file: %s</info>', $phelFileFilter));
                }
            }
        }

        if ($input->getOption('clear-opcache') && function_exists('opcache_reset')) {
            @opcache_reset();
        }

        try {
            /** @var string|null $path */
            $path = $input->getArgument('path');

            // Auto-detect entry point if no path provided
            if ($path === null || $path === '') {
                $path = $this->getFacade()->autoDetectEntryPoint();
                if ($path === null) {
                    $output->writeln('<error>No entry point found. Create src/phel/core.phel or specify a path.</error>');
                    return self::FAILURE;
                }

                if ($output->isVerbose()) {
                    $output->writeln(sprintf('<info>Auto-detected entry point: %s</info>', $path));
                }
            }

            /** @var list<string>|string|null $rawArgv */
            $rawArgv = $input->getArgument('argv');
            $userArgv = is_array($rawArgv) ? $rawArgv : [];

            // Set up normalized runtime args before executing the script
            Phel::setupRuntimeArgs($path, $userArgv);

            if (file_exists($path)) {
                $this->getFacade()->runFile($path);
            } else {
                $this->getFacade()->runNamespace($path);
            }

            if ($input->getOption('with-time')) {
                $output->writeln((new ResourceUsageFormatter())->resourceUsageSinceStartOfRequest());
            }

            DebugLineTap::disable();

            return self::SUCCESS;
        } catch (CompilerException $e) {
            $this->getFacade()->writeLocatedException($output, $e);
        } catch (Throwable $e) {
            $this->getFacade()->writeStackTrace($output, $e);
        } finally {
            DebugLineTap::disable();
        }

        return self::FAILURE;
    }

}
