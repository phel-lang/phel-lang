<?php

declare(strict_types=1);

namespace Phel\Build\Command;

use Gacela\Framework\FacadeResolverAwareTrait;
use Phel\Build\BuildFacade;
use Phel\Build\Compile\BuildOptions;
use Phel\Compiler\Exceptions\CompilerException;
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
    use FacadeResolverAwareTrait;

    private const OPTION_CACHE = 'cache';
    private const OPTION_SOURCE_MAP = 'source-map';

    protected function configure(): void
    {
        $this->setName('compile')
            ->setDescription('Compile the current project')
            ->addOption(self::OPTION_CACHE, null, InputOption::VALUE_NEGATABLE, 'Enable cache', true)
            ->addOption(self::OPTION_SOURCE_MAP, null, InputOption::VALUE_NEGATABLE, 'Enable source maps', true);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->getFacade()->registerExceptionHandler();
        $buildOptions = $this->getBuildOptions($input);

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

    protected function facadeClass(): string
    {
        return BuildFacade::class;
    }

    private function getBuildOptions(InputInterface $input): BuildOptions
    {
        return new BuildOptions(
            $input->getOption(self::OPTION_CACHE) === true,
            $input->getOption(self::OPTION_SOURCE_MAP) === true
        );
    }

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
                )
            );
        }
    }
}
