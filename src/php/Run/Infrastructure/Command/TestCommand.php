<?php

declare(strict_types=1);

namespace Phel\Run\Infrastructure\Command;

use Gacela\Framework\DocBlockResolverAwareTrait;
use Phel\Build\Domain\Extractor\NamespaceInformation;
use Phel\Compiler\Domain\Exceptions\CompilerException;
use Phel\Compiler\Infrastructure\CompileOptions;
use Phel\Run\Domain\Test\TestCommandOptions;
use Phel\Run\RunFacade;
use SebastianBergmann\Timer\ResourceUsageFormatter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

use function sprintf;

/**
 * @method RunFacade getFacade()
 */
final class TestCommand extends Command
{
    use DocBlockResolverAwareTrait;

    public const COMMAND_NAME = 'test';

    private const ARG_PATHS = 'paths';

    private const OPT_FILTER = 'filter';

    private const OPT_TESTDOX = 'testdox';

    protected function configure(): void
    {
        $this->setName(self::COMMAND_NAME)
            ->setDescription(
                'Tests the given files. If no filenames are provided all tests in the "tests" directory are executed',
            )
            ->addArgument(
                self::ARG_PATHS,
                InputArgument::IS_ARRAY | InputArgument::OPTIONAL,
                'The file paths that you want to test.',
                [],
            )->addOption(
                self::OPT_FILTER,
                'f',
                InputOption::VALUE_OPTIONAL,
                'Filter by test names.',
            )->addOption(
                self::OPT_TESTDOX,
                null,
                InputOption::VALUE_NONE,
                'Report test execution progress in TestDox format.',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            /** @var list<string> $paths */
            $paths = (array) $input->getArgument(self::ARG_PATHS);
            $namespacesInformation = $this->getFacade()->getDependenciesFromPaths($paths);

            $phelCode = $this->generatePhelCode($input, $namespacesInformation);

            foreach ($namespacesInformation as $info) {
                $this->getFacade()->evalFile($info);
            }

            $result = $this->getFacade()->eval($phelCode, new CompileOptions());

            $output->writeln((new ResourceUsageFormatter())->resourceUsageSinceStartOfRequest());

            return ($result) ? self::SUCCESS : self::FAILURE;
        } catch (CompilerException $e) {
            $this->getFacade()->writeLocatedException($output, $e);
        } catch (Throwable $e) {
            $this->getFacade()->writeStackTrace($output, $e);
        }

        return self::FAILURE;
    }

    private function generatePhelCode(InputInterface $input, array $namespacesInformation): string
    {
        return sprintf(
            '(do (phel\test/run-tests %s %s) (phel\test/successful?))',
            TestCommandOptions::fromArray([
                TestCommandOptions::FILTER => (string) $input->getOption(self::OPT_FILTER),
                TestCommandOptions::TESTDOX => (bool) $input->getOption(self::OPT_TESTDOX),
            ])->asPhelHashMap(),
            $this->namespacesAsString($namespacesInformation),
        );
    }

    /**
     * @param  list<NamespaceInformation>  $namespacesInfo
     */
    private function namespacesAsString(array $namespacesInfo): string
    {
        $namespaces = [];
        foreach ($namespacesInfo as $info) {
            $namespaces[] = "'" . $info->getNamespace();
        }

        return implode(' ', $namespaces);
    }
}
