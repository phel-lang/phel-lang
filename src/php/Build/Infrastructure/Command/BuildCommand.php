<?php

declare(strict_types=1);

namespace Phel\Build\Infrastructure\Command;

use Gacela\Framework\ServiceResolver\ServiceMap;
use Gacela\Framework\ServiceResolverAwareTrait;
use Phel\Build\BuildFacade;
use Phel\Build\Domain\Compile\BuildOptions;
use Phel\Build\Domain\Compile\CompiledFile;
use Phel\Compiler\Domain\Exceptions\CompilerException;
use Phel\Shared\ResourceUsageFormatter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

use function array_filter;
use function count;
use function sprintf;

#[ServiceMap(method: 'getFacade', className: BuildFacade::class)]
final class BuildCommand extends Command
{
    use ServiceResolverAwareTrait;

    private const string OPTION_CACHE = 'cache';

    private const string OPTION_SOURCE_MAP = 'source-map';

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
        $fresh = array_filter($compiledProject, static fn(CompiledFile $f): bool => !$f->isCached());
        $cachedCount = count($compiledProject) - count($fresh);

        $index = 0;
        foreach ($fresh as $compiledFile) {
            $output->writeln(
                sprintf(
                    "#%d | Namespace: %s\nSource: %s\nTarget: %s\n",
                    $index,
                    $compiledFile->getNamespace(),
                    $compiledFile->getSourceFile(),
                    $compiledFile->getTargetFile(),
                ),
            );
            ++$index;
        }

        $this->printSummary($output, count($fresh), $cachedCount);
    }

    private function printSummary(OutputInterface $output, int $freshCount, int $cachedCount): void
    {
        $total = $freshCount + $cachedCount;
        if ($total === 0) {
            $output->writeln('No Phel namespaces found to build.');
            return;
        }

        $outputDir = $this->getFacade()->getOutputDirectory();

        if ($freshCount === 0) {
            $output->writeln(sprintf(
                'No changes detected. %d file%s reused from cache. Compiled output: %s',
                $cachedCount,
                $cachedCount === 1 ? '' : 's',
                $outputDir,
            ));
            return;
        }

        $output->writeln(sprintf(
            'Compiled %d file%s (%d reused from cache). Output directory: %s',
            $freshCount,
            $freshCount === 1 ? '' : 's',
            $cachedCount,
            $outputDir,
        ));
    }
}
