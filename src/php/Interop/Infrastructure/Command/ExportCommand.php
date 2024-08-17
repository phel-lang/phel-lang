<?php

declare(strict_types=1);

namespace Phel\Interop\Infrastructure\Command;

use Gacela\Framework\DocBlockResolverAwareTrait;
use Phel\Compiler\Domain\Exceptions\CompilerException;
use Phel\Interop\InteropFacade;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

use function sprintf;

/**
 * @method InteropFacade getFacade()
 */
final class ExportCommand extends Command
{
    use DocBlockResolverAwareTrait;

    protected function configure(): void
    {
        $this->setName('export')
            ->setDescription('Export all definitions with the meta data `{:export true}` as PHP classes');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->generateExportCode($output);

            return self::SUCCESS;
        } catch (CompilerException $e) {
            $this->getFacade()->writeLocatedException($output, $e);
        } catch (Throwable $e) {
            $this->getFacade()->writeStackTrace($output, $e);
        }

        return self::FAILURE;
    }

    private function generateExportCode(OutputInterface $output): void
    {
        $output->writeln('Exported namespaces:');
        $wrappers = $this->getFacade()->generateExportCode();

        if (empty($wrappers)) {
            $output->writeln('No functions were found to be exported');
        }

        foreach ($wrappers as $i => $wrapper) {
            $output->writeln(sprintf(
                '  %d) %s',
                $i + 1,
                $wrapper->relativeFilenamePath(),
            ));
        }
    }
}
