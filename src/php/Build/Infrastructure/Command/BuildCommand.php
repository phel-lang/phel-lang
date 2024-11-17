<?php

declare(strict_types=1);

namespace Phel\Build\Infrastructure\Command;

use Gacela\Framework\DocBlockResolverAwareTrait;
use Phel\Build\BuildFacade;
use Phel\Build\Domain\Compile\BuildOptions;
use Phel\Build\Domain\Compile\CompiledFile;
use Phel\Compiler\Domain\Exceptions\CompilerException;
use SebastianBergmann\Timer\ResourceUsageFormatter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

use function sprintf;

/**
 * @method BuildFacade getFacade()
 */
final class BuildCommand extends Command
{
    use DocBlockResolverAwareTrait;

    private const OPTION_CACHE = 'cache';

    private const OPTION_SOURCE_MAP = 'source-map';

    protected function configure(): void
    {
        $this->setName('build')
            ->setAliases(['compile'])
            ->setDescription('Build the current project')
            ->addOption(self::OPTION_CACHE, null, InputOption::VALUE_NEGATABLE, 'Enable cache', true)
            ->addOption(self::OPTION_SOURCE_MAP, null, InputOption::VALUE_NEGATABLE, 'Enable source maps', true);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $buildOptions = $this->getBuildOptions($input);

        try {
            $compiledProject = $this->getFacade()->compileProject($buildOptions);
            $this->printOutput($output, $compiledProject);
        } catch (CompilerException $e) {
            $this->getFacade()->writeLocatedException($output, $e);
        } catch (Throwable $e) {
            $this->getFacade()->writeStackTrace($output, $e);
        }

        $output->writeln((new ResourceUsageFormatter())->resourceUsageSinceStartOfRequest());

        return self::SUCCESS;
    }

    private function getBuildOptions(InputInterface $input): BuildOptions
    {
        return new BuildOptions(
            $input->getOption(self::OPTION_CACHE) === true,
            $input->getOption(self::OPTION_SOURCE_MAP) === true,
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
