<?php

declare(strict_types=1);

namespace Phel\Run\Infrastructure\Command;

use Gacela\Framework\DocBlockResolverAwareTrait;
use Phel\Compiler\Domain\Exceptions\CompilerException;
use Phel\Run\RunFacade;
use SebastianBergmann\Timer\ResourceUsageFormatter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

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
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $namespace = $this->getNamespace($input);
            $result = $this->executeNamespace($namespace);

            if ($result === '') {
                $this->renderNoResultOutput($output, $namespace);
            } else {
                $output->write($result);
            }

            if ($input->getOption('with-time')) {
                $output->writeln((new ResourceUsageFormatter())->resourceUsageSinceStartOfRequest());
            }

            return self::SUCCESS;
        } catch (CompilerException $e) {
            $this->getFacade()->writeLocatedException($output, $e);
        } catch (Throwable $e) {
            $this->getFacade()->writeStackTrace($output, $e);
        }

        return self::FAILURE;
    }

    private function getNamespace(InputInterface $input): string
    {
        /** @var string $fileOrNamespace */
        $fileOrNamespace = $input->getArgument('path');
        if (file_exists($fileOrNamespace)) {
            return $this->getFacade()
                ->getNamespaceFromFile($fileOrNamespace)
                ->getNamespace();
        }

        return $fileOrNamespace;
    }

    private function executeNamespace(string $namespace): string
    {
        ob_start();
        $this->getFacade()->runNamespace($namespace);

        return ob_get_clean();
    }

    private function renderNoResultOutput(OutputInterface $output, string $namespace): void
    {
        $output->writeln(
            <<<EOF
            <error>No rendered output after running namespace: "{$namespace}"</> 
            
            <comment>Please verify that at least one of the following applies:
            - The file exists
            - The namespace exists</>
            
            You can ignore this message with `-q|--quiet`
            EOF
        );
    }
}
