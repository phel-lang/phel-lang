<?php

declare(strict_types=1);

namespace Phel\Run\Infrastructure\Command;

use Gacela\Framework\ServiceResolver\ServiceMap;
use Gacela\Framework\ServiceResolverAwareTrait;
use Phel\Compiler\Domain\Exceptions\CompilerException;
use Phel\Phel;
use Phel\Run\Infrastructure\Service\DebugLineTap;
use Phel\Run\RunFacade;
use RuntimeException;
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
                InputArgument::REQUIRED,
                'The file path or namespace to execute',
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
            /** @var string $path */
            $path = $input->getArgument('path');

            /** @var list<string>|string|null $rawArgv */
            $rawArgv = $input->getArgument('argv');
            $userArgv = is_array($rawArgv) ? $rawArgv : [];

            // Set up normalized runtime args before executing the script
            Phel::setupRuntimeArgs($path, $userArgv);

            $result = file_exists($path) ? $this->executeFile($path) : $this->executeNamespace($path);

            $identifier = $path;

            if ($result === '') {
                $this->renderNoResultOutput($output, $identifier);
            } else {
                $output->write($result);
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

    private function executeNamespace(string $namespace): string
    {
        ob_start();
        $this->getFacade()->runNamespace($namespace);

        $buffer = ob_get_clean();

        if ($buffer === false) {
            throw new RuntimeException('Unable to capture namespace execution output.');
        }

        return $buffer;
    }

    private function executeFile(string $filename): string
    {
        ob_start();
        $this->getFacade()->runFile($filename);

        $buffer = ob_get_clean();

        if ($buffer === false) {
            throw new RuntimeException('Unable to capture file execution output.');
        }

        return $buffer;
    }

    private function renderNoResultOutput(OutputInterface $output, string $identifier): void
    {
        $output->writeln(
            <<<EOF
            <error>No rendered output after running namespace: "{$identifier}"</>
            
            <comment>Please verify that at least one of the following applies:
            - The file exists
            - The namespace exists</>
            
            You can ignore this message with `-q|--quiet`
            EOF
        );
    }
}
