<?php

declare(strict_types=1);

namespace Phel\Formatter\Infrastructure\Command;

use Gacela\Framework\ServiceResolver\ServiceMap;
use Gacela\Framework\ServiceResolverAwareTrait;
use Phel\Formatter\FormatterConfig;
use Phel\Formatter\FormatterFacade;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function array_unique;
use function array_values;
use function count;
use function sprintf;

/**
 * @method FormatterFacade getFacade()
 * @method FormatterConfig getConfig()
 *
 * @internal
 */
#[ServiceMap(method: 'getFacade', className: FormatterFacade::class)]
#[ServiceMap(method: 'getConfig', className: FormatterConfig::class)]
final class FormatCommand extends Command
{
    use ServiceResolverAwareTrait;

    private const string OPT_EXCLUDE = 'exclude';

    protected function configure(): void
    {
        $this->setName('format')
            ->setDescription('Formats the given files')
            ->setHelp(<<<'HELP'
Reformats `.phel` files in place (defaults to the project's format dirs).

<info>Examples:</info>
  <comment>phel format</comment>                Format all configured directories
  <comment>phel format src/main.phel --dry-run</comment>   Preview changes only
  <comment>phel format --exclude='src/*_data.phel'</comment>   Leave generated data files alone
HELP)
            ->setAliases(['fmt'])
            ->addArgument(
                'paths',
                InputArgument::IS_ARRAY,
                'The file paths that you want to format.',
                $this->getConfig()->getFormatDirs(),
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Report files that would be reformatted without modifying them. Exits non-zero when any file would change.',
            )
            ->addOption(
                self::OPT_EXCLUDE,
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Skip files matching this glob (fnmatch; `*` spans directories), matched against the path as found and relative to the working directory. Repeatable; unioned with the `format-exclude` config key.',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var list<string> $paths */
        $paths = $input->getArgument('paths');
        $dryRun = (bool) $input->getOption('dry-run');
        /** @var list<string> $excludeOption */
        $excludeOption = (array) $input->getOption(self::OPT_EXCLUDE);
        $exclude = array_values(array_unique([...$this->getConfig()->getFormatExclude(), ...$excludeOption]));

        $result = $this->getFacade()->format($paths, $output, $dryRun, $exclude);

        if ($result->hasChanges()) {
            $output->writeln($dryRun ? 'Would reformat:' : 'Formatted files:');

            foreach ($result->changedPaths() as $k => $filePath) {
                $output->writeln(sprintf('  %d) %s', $k + 1, $filePath));
            }
        } else {
            $output->writeln($dryRun ? 'No files would be reformatted.' : 'No files were formatted.');
        }

        // A file the formatter could not even read or parse is a hard failure:
        // reporting it and still exiting 0 would let a `--dry-run` CI gate pass
        // over broken sources.
        if ($result->hasFailures()) {
            $output->writeln(sprintf('%d file(s) could not be formatted.', count($result->failedPaths())));

            return self::FAILURE;
        }

        if ($dryRun && $result->hasChanges()) {
            $output->writeln(sprintf('%d file(s) need reformatting.', count($result->changedPaths())));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
