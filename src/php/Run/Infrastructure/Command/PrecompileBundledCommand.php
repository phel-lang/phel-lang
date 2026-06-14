<?php

declare(strict_types=1);

namespace Phel\Run\Infrastructure\Command;

use Gacela\Framework\ServiceResolver\ServiceMap;
use Gacela\Framework\ServiceResolverAwareTrait;
use Phel\Run\RunFacade;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

use function is_string;
use function sprintf;

/**
 * Hidden subcommand: precompiles the bundled `phel.*` stdlib into a read-only,
 * content-addressed bundle at the given directory. Invoked at distribution
 * build time (see build/build-phar.php) so a cold `phel run` reuses the
 * precompiled stdlib instead of recompiling it. Not for direct use.
 *
 * @method RunFacade getFacade()
 */
#[ServiceMap(method: 'getFacade', className: RunFacade::class)]
final class PrecompileBundledCommand extends Command
{
    use ServiceResolverAwareTrait;

    public const string COMMAND_NAME = '_precompile-bundled';

    protected function configure(): void
    {
        $this->setName(self::COMMAND_NAME)
            ->setDescription('Internal: precompile the bundled stdlib. Not for direct use.')
            ->setHidden(true)
            ->addArgument(
                'target-dir',
                InputArgument::REQUIRED,
                'Directory to write the content-addressed precompiled bundle into',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $targetDir = $input->getArgument('target-dir');
        if (!is_string($targetDir) || $targetDir === '') {
            $output->writeln('<error>A target directory is required.</error>');

            return self::FAILURE;
        }

        try {
            $count = $this->getFacade()->precompileBundledStdlib($targetDir);
        } catch (Throwable $throwable) {
            $output->writeln(sprintf('<error>Precompile failed: %s</error>', $throwable->getMessage()));

            return self::FAILURE;
        }

        $output->writeln(sprintf('Precompiled %d bundled module(s) into %s', $count, $targetDir));

        return self::SUCCESS;
    }
}
