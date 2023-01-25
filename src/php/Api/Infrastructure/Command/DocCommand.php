<?php

declare(strict_types=1);

namespace Phel\Api\Infrastructure\Command;

use Gacela\Framework\DocBlockResolverAwareTrait;
use Phel\Api\ApiFacade;
use Phel\Api\Transfer\PhelFunction;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function strlen;

/**
 * @method ApiFacade getFacade()
 */
final class DocCommand extends Command
{
    use DocBlockResolverAwareTrait;

    private const OPTION_NAMESPACES = 'ns';

    protected function configure(): void
    {
        $this->setName('doc')
            ->setDescription('Display the docs for any/all phel functions.')
            ->addOption(
                self::OPTION_NAMESPACES,
                null,
                InputOption::VALUE_OPTIONAL,
                'Specify which namespaces to load (comma separated)',
                'phel\\core',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $namespaces = explode(',', $input->getOption(self::OPTION_NAMESPACES));

        $groupedFunctions = $this->getFacade()->getGroupedFunctions($namespaces);
        $this->printFunctions($output, $groupedFunctions);

        return self::SUCCESS;
    }

    /**
     * @param array<string,list<PhelFunction>> $groupedFunctions
     */
    private function printFunctions(OutputInterface $output, array $groupedFunctions): void
    {
        [$normalized, $longestFuncNameLength] = $this->normalizeGroupedFunctions($groupedFunctions);

        foreach ($normalized as $func) {
            $output->writeln(
                sprintf(
                    '  %s: %s - %s',
                    str_pad($func['name'], $longestFuncNameLength),
                    $func['signature'],
                    $func['description'],
                ),
            );
        }
    }

    /**
     * @return array{
     *   0: list<array{
     *     name: string,
     *     signature: string,
     *     doc: string,
     *     description: string,
     *   }>,
     *   1: int
     * }
     */
    private function normalizeGroupedFunctions(array $groupedFunctions): array
    {
        $longestFuncNameLength = 5;
        $normalized = [];
        foreach ($groupedFunctions as $functions) {
            foreach ($functions as $function) {
                if (strlen($function->fnName()) > $longestFuncNameLength) {
                    $longestFuncNameLength = strlen($function->fnName());
                }
                $normalized[] = [
                    'name' => $function->fnName(),
                    'signature' => $function->fnSignature(),
                    'doc' => $function->doc(),
                    'description' => preg_replace('/\r?\n/', '', $function->description()),
                ];
            }
        }
        return [$normalized, $longestFuncNameLength];
    }
}
