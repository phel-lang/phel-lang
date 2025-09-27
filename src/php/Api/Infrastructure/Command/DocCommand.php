<?php

declare(strict_types=1);

namespace Phel\Api\Infrastructure\Command;

use Gacela\Framework\DocBlockResolverAwareTrait;
use InvalidArgumentException;
use Phel\Api\ApiFacade;
use Phel\Api\Transfer\PhelFunction;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Symfony\Component\Console\Terminal;

use function in_array;
use function sprintf;

/**
 * @method ApiFacade getFacade()
 */
final class DocCommand extends Command
{
    use DocBlockResolverAwareTrait;

    private const string OPTION_NAMESPACES = 'ns';

    private const string OPTION_FORMAT = 'format';

    private const array AVAILABLE_FORMATS = ['table', 'json'];

    protected function configure(): void
    {
        $this->setName('doc')
            ->setDescription('Display the docs for any/all phel functions')
            ->addArgument('search', InputArgument::OPTIONAL, 'Search input that look for a similar function name', '')
            ->addOption(
                self::OPTION_NAMESPACES,
                null,
                InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
                'Specify which namespaces to load.',
                [],
            )
            ->addOption(
                self::OPTION_FORMAT,
                null,
                InputOption::VALUE_REQUIRED,
                'Specify the output format.',
                'table',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $namespaces = $this->normalizeNamespaces($input->getOption(self::OPTION_NAMESPACES));
        $phelFunctions = $this->getFacade()->getPhelFunctions($namespaces);

        $search = $input->getArgument('search');
        $normalized = $this->normalizeGroupedFunctions($phelFunctions, $search);

        $format = strtolower((string)$input->getOption(self::OPTION_FORMAT));
        if (!in_array($format, self::AVAILABLE_FORMATS, true)) {
            $message = sprintf(
                'Invalid format "%s". Allowed values: %s',
                $format,
                implode(', ', self::AVAILABLE_FORMATS),
            );

            throw new InvalidArgumentException($message);
        }

        if ($format === 'json') {
            $this->printFunctionsAsJson($output, $normalized);
            return self::SUCCESS;
        }

        $this->printFunctionsAsTable($output, $normalized);

        return self::SUCCESS;
    }

    private function normalizeNamespaces(array $namespaces): array
    {
        array_walk($namespaces, static function (string &$ns): void {
            if (!in_array($ns, ['core', 'http', 'html', 'test', 'json'])) {
                return;
            }

            if (str_starts_with($ns, 'phel\\')) {
                return;
            }

            $ns = 'phel\\' . $ns;
        });

        return $namespaces;
    }

    /**
     * @param list<array{
     *     percent:int,
     *     name:string,
     *     signature:string,
     *     doc:string,
     *     description:string,
     *     githubUrl:string,
     *     docUrl:string
     * }> $phelFunctions
     */
    private function printFunctionsAsTable(OutputInterface $output, array $phelFunctions): void
    {
        [$width1, $width2, $width3] = $this->calculateWithProportionalToCurrentScreen();

        $table = (new Table($output))
            ->setHeaders(['function', 'signature', 'description'])
            ->setColumnMaxWidth(0, $width1)
            ->setColumnMaxWidth(1, $width2)
            ->setColumnMaxWidth(2, $width3);

        foreach ($phelFunctions as $func) {
            $table->addRow([$func['name'], $func['signature'], $func['description']]);
        }

        $table->render();
    }

    /**
     * @param list<array{
     *     percent:int,
     *     name:string,
     *     signature:string,
     *     doc:string,
     *     description:string,
     *     githubUrl:string,
     *     docUrl:string
     * }> $phelFunctions
     */
    private function printFunctionsAsJson(OutputInterface $output, array $phelFunctions): void
    {
        $jsonData = array_map(static function (array $func): array {
            unset($func['percent']);
            return $func;
        }, $phelFunctions);

        $output->writeln(json_encode($jsonData, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
    }

    /**
     * @return array{0:int, 1:int, 2:int}
     */
    private function calculateWithProportionalToCurrentScreen(): array
    {
        $colCount = (new Terminal())->getWidth();
        $proportion1 = 25;
        $proportion2 = 40;
        $proportion3 = 50;
        $totalProportion = $proportion1 + $proportion2 + $proportion3;
        $width1 = (int)(($proportion1 / $totalProportion) * $colCount) - 5;
        $width2 = (int)(($proportion2 / $totalProportion) * $colCount) - 5;
        $width3 = $colCount - ($width1 + $width2 + 10);

        return [$width1, $width2, $width3];
    }

    /**
     * @param list<PhelFunction> $phelFunctions
     *
     * @return list<array{
     *   percent: int,
     *   name: string,
     *   signature: string,
     *   doc: string,
     *   description: string,
     *   githubUrl: string,
     *   docUrl: string,
     * }>
     */
    private function normalizeGroupedFunctions(array $phelFunctions, string $search): array
    {
        $normalized = [];

        foreach ($phelFunctions as $phelFunction) {
            $fnName = $phelFunction->namespace . '/' . $phelFunction->name;
            $percent = 0.0;
            similar_text($fnName, $search, $percent);
            if ($search && $percent < 45) {
                continue;
            }

            $description = preg_replace('/\r?\n/', '', $phelFunction->description) ?? '';

            $normalized[] = [
                'percent' => (int) round($percent),
                'name' => $fnName,
                'signature' => $phelFunction->signature,
                'doc' => $phelFunction->doc,
                'description' => $description,
                'githubUrl' => $phelFunction->githubUrl,
                'docUrl' => $phelFunction->docUrl,
            ];
        }

        usort($normalized, static fn (array $a, array $b): int => $b['percent'] <=> $a['percent']);

        return $normalized;
    }
}
