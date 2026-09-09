<?php

declare(strict_types=1);

namespace Phel\Build\Infrastructure\Command;

use Gacela\Console\Infrastructure\Command\CacheClearCommand as GacelaCacheClearCommand;
use Gacela\Framework\ServiceResolver\ServiceMap;
use Gacela\Framework\ServiceResolverAwareTrait;
use Phel\Build\BuildFacade;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @method BuildFacade getFacade()
 *
 * @internal
 */
#[ServiceMap(method: 'getFacade', className: BuildFacade::class)]
final class CacheClearCommand extends Command
{
    use ServiceResolverAwareTrait;

    protected function configure(): void
    {
        $this->setName('cache:clear')
            ->setDescription('Clear the temp, cache and OPcache directories')
            ->setHelp(<<<'HELP'
Removes compiled-code, namespace, and temp caches, and empties the OPcache file
cache under the project state directory; the next run rebuilds them.

The OPcache directory is emptied in place rather than removed, because PHP
aborts at startup when opcache.file_cache points at a path that is missing.

<info>Example:</info>
  <comment>phel cache:clear</comment>
HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $clearedPaths = $this->getFacade()->clearCache();

        foreach ($clearedPaths as $path) {
            $output->writeln('Cleared: ' . $path);
        }

        $gacelaStatus = new GacelaCacheClearCommand()->run(new ArrayInput([]), $output);
        if ($gacelaStatus !== Command::SUCCESS) {
            return $gacelaStatus;
        }

        $output->writeln('<info>Cache cleared successfully.</info>');

        return self::SUCCESS;
    }
}
