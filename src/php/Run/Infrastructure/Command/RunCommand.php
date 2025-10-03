<?php

declare(strict_types=1);

namespace Phel\Run\Infrastructure\Command;

use Gacela\Framework\DocBlockResolverAwareTrait;
use Phel\Compiler\Domain\Exceptions\CompilerException;
use Phel\Debug\DebugLineTap;
use Phel\Run\RunFacade;
use SebastianBergmann\Timer\ResourceUsageFormatter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

use function function_exists;
use function is_string;
use function sprintf;

/**
 * @method RunFacade getFacade()
 */
final class RunCommand extends Command
{
    use DocBlockResolverAwareTrait;

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
                'd',
                InputOption::VALUE_OPTIONAL,
                'Enable line-by-line debug tracing (optional: log file path, default: ./phel-debug.log)',
                false,
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $debugOption = $input->getOption('debug');
        if ($debugOption !== false) {
            $logPath = is_string($debugOption) && $debugOption !== '' ? $debugOption : './phel-debug.log';
            DebugLineTap::enable($logPath);
            if ($output->isVerbose()) {
                $output->writeln(sprintf('<info>Debug tracing enabled. Logging to: %s</info>', $logPath));
            }
        }

        if ($input->getOption('clear-opcache') && function_exists('opcache_reset')) {
            @opcache_reset();
        }

        try {
            /** @var string $path */
            $path = $input->getArgument('path');
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

        return ob_get_clean();
    }

    private function executeFile(string $filename): string
    {
        ob_start();
        $this->getFacade()->runFile($filename);

        return ob_get_clean();
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
