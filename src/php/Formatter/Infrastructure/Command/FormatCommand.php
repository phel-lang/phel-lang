<?php

declare(strict_types=1);

namespace Phel\Formatter\Infrastructure\Command;

use Gacela\Framework\DocBlockResolverAwareTrait;
use Phel\Formatter\FormatterFacade;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @method FormatterFacade getFacade()
 */
final class FormatCommand extends Command
{
    use DocBlockResolverAwareTrait;

    protected function configure(): void
    {
        $this->setName('format')
            ->setDescription('Formats the given files.')
            ->addArgument(
                'paths',
                InputArgument::IS_ARRAY | InputArgument::REQUIRED,
                'The file paths that you want to format.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var list<string> $paths */
        $paths = $input->getArgument('paths');

        $formattedFilePaths = $this->getFacade()->format($paths, $output);

        if (empty($formattedFilePaths)) {
            $output->writeln('No files were formatted.');

            return self::SUCCESS;
        }

        $output->writeln('Formatted files:');

        foreach ($formattedFilePaths as $k => $filePath) {
            $output->writeln(sprintf('  %d) %s', $k + 1, $filePath));
        }

        return self::SUCCESS;
    }
}
