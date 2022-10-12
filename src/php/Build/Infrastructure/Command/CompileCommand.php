<?php

declare(strict_types=1);

namespace Phel\Build\Infrastructure\Command;

use Gacela\Framework\DocBlockResolverAwareTrait;
use Phel\Build\BuildFacade;
use Phel\Build\Domain\Compile\BuildOptions;
use Phel\Build\Domain\Compile\CompiledFile;
use Phel\Compiler\Domain\Exceptions\CompilerException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * @method BuildFacade getFacade()
 */
final class CompileCommand extends Command
{
    use DocBlockResolverAwareTrait;

    private const OPTION_CACHE = 'cache';
    private const OPTION_SOURCE_MAP = 'source-map';
    private const OPTION_MAIN_NS = 'main-ns';

    protected function configure(): void
    {
        $this->setName('compile')
            ->setDescription('Compile the current project')
            ->addOption(self::OPTION_CACHE, null, InputOption::VALUE_NEGATABLE, 'Enable cache', true)
            ->addOption(self::OPTION_SOURCE_MAP, null, InputOption::VALUE_NEGATABLE, 'Enable source maps', true)
            ->addOption(self::OPTION_MAIN_NS, null, InputOption::VALUE_OPTIONAL, 'Define the main namespace');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->getFacade()->registerExceptionHandler();
        $buildOptions = $this->createBuildOptions($input);

        try {
            $compiledProject = $this->getFacade()->compileProject($buildOptions);
            $this->printOutput($output, $compiledProject);
        } catch (CompilerException $e) {
            $this->getFacade()->writeLocatedException($output, $e);
        } catch (Throwable $e) {
            $this->getFacade()->writeStackTrace($output, $e);
        }

        return self::SUCCESS;
    }

    private function createBuildOptions(InputInterface $input): BuildOptions
    {
        return new BuildOptions(
            $input->getOption(self::OPTION_CACHE) === true,
            $input->getOption(self::OPTION_SOURCE_MAP) === true,
            $input->getOption(self::OPTION_MAIN_NS),
        );
    }

    /**
     * @param list<CompiledFile> $compiledProject
     */
    private function printOutput(OutputInterface $output, array $compiledProject): void
    {
        foreach ($compiledProject as $i => $compiledFile) {
            $output->writeln(
                sprintf(
                    "#%d | Namespace: %s\nSource: %s\nTarget: %s\n",
                    $i,
                    $compiledFile->getNamespace(),
                    $compiledFile->getSourceFile(),
                    $compiledFile->getTargetFile(),
                ),
            );
        }
    }
}
