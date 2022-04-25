<?php

declare(strict_types=1);

namespace Phel\Run\Command;

use Gacela\Framework\FacadeResolverAwareTrait;
use Phel\Build\Extractor\NamespaceInformation;
use Phel\Compiler\Exceptions\CompilerException;
use Phel\Run\Domain\Test\TestCommandOptions;
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
final class TestCommand extends Command
{
    use FacadeResolverAwareTrait;

    public const COMMAND_NAME = 'test';

    protected function configure(): void
    {
        $this->setName(self::COMMAND_NAME)
            ->setDescription(
                'Tests the given files. If no filenames are provided all tests in the "tests" directory are executed.'
            )
            ->addArgument(
                'paths',
                InputArgument::IS_ARRAY | InputArgument::OPTIONAL,
                'The file paths that you want to test.',
                []
            )->addOption(
                'filter',
                'f',
                InputOption::VALUE_OPTIONAL,
                'Filter by test names.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->getFacade()->registerExceptionHandler();

            /** @var list<string> $paths */
            $paths = (array)$input->getArgument('paths');
            $namespacesInformation = $this->getFacade()->getDependenciesFromPaths($paths);

            foreach ($namespacesInformation as $info) {
                $this->getFacade()->evalFile($info);
            }

            $phelCode = sprintf(
                '(do (phel\test/run-tests %s %s) (phel\test/successful?))',
                TestCommandOptions::fromArray([
                    TestCommandOptions::FILTER => (string)$input->getOption('filter'),
                ])->asPhelHashMap(),
                $this->namespacesAsString($namespacesInformation),
            );

            $result = $this->getFacade()->eval($phelCode);

            $output->writeln((new ResourceUsageFormatter())->resourceUsageSinceStartOfRequest());

            return ($result) ? self::SUCCESS : self::FAILURE;
        } catch (CompilerException $e) {
            $this->getFacade()->writeLocatedException($output, $e);
        } catch (Throwable $e) {
            $this->getFacade()->writeStackTrace($output, $e);
        }

        return self::FAILURE;
    }

    protected function facadeClass(): string
    {
        return RunFacade::class;
    }

    /**
     * @param list<NamespaceInformation> $namespacesInfo
     */
    private function namespacesAsString(array $namespacesInfo): string
    {
        $namespaces = [];
        foreach ($namespacesInfo as $info) {
            $namespaces[] = "'{$info->getNamespace()}";
        }

        return implode(' ', $namespaces);
    }
}
