<?php

declare(strict_types=1);

namespace Phel\Api\Infrastructure\Command;

use Gacela\Framework\ServiceResolver\ServiceMap;
use Gacela\Framework\ServiceResolverAwareTrait;
use Phel\Api\ApiFacade;
use Phel\Api\Application\PhelFileIterator;
use Phel\Shared\Api\Diagnostic;
use Phel\Shared\InvocationError;
use Phel\Shared\ScalarCoercion;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use UnexpectedValueException;

use function file_get_contents;
use function is_dir;
use function is_file;
use function is_readable;
use function iterator_to_array;
use function json_encode;
use function sort;
use function sprintf;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;

/**
 * @method ApiFacade getFacade()
 *
 * @internal
 */
#[ServiceMap(method: 'getFacade', className: ApiFacade::class)]
final class AnalyzeCommand extends Command
{
    use ServiceResolverAwareTrait;

    public const string DESCRIPTION = 'Run semantic analysis on Phel source files or directories and emit JSON diagnostics';

    private const string ARG_PATHS = 'paths';

    protected function configure(): void
    {
        $this->setName('analyze')
            ->setDescription(self::DESCRIPTION)
            ->setHelp(<<<'HELP'
Emits analyzer diagnostics (unresolved symbols, arity errors, ...) as JSON.
Directories are walked for .phel files. Exits 1 when any diagnostic is an error.

<info>Examples:</info>
  <comment>phel analyze src/main.phel</comment>
  <comment>phel analyze src</comment>
HELP)
            ->addArgument(self::ARG_PATHS, InputArgument::REQUIRED | InputArgument::IS_ARRAY, 'Paths to .phel files or directories');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $files = [];
        foreach (ScalarCoercion::toStringList($input->getArgument(self::ARG_PATHS)) as $path) {
            if (is_dir($path)) {
                // An unreadable directory iterates as empty, which would pass
                // as a clean result for source that was never analyzed.
                if (!is_readable($path)) {
                    return InvocationError::report($output, sprintf('Unable to read directory: %s', $path));
                }

                try {
                    $found = iterator_to_array(PhelFileIterator::iterate($path), false);
                } catch (UnexpectedValueException $unexpectedValueException) {
                    return InvocationError::report($output, sprintf('Unable to read directory: %s', $unexpectedValueException->getMessage()));
                }

                sort($found);
                $files = [...$files, ...$found];

                continue;
            }

            if (!is_file($path)) {
                return InvocationError::report($output, sprintf('File not found: %s', $path));
            }

            $files[] = $path;
        }

        $diagnostics = [];
        foreach ($files as $file) {
            $source = file_get_contents($file);
            if ($source === false) {
                return InvocationError::report($output, sprintf('Unable to read file: %s', $file));
            }

            $diagnostics = [...$diagnostics, ...$this->getFacade()->analyzeSource($source, $file)];
        }

        $payload = array_map(static fn(Diagnostic $d): array => $d->toArray(), $diagnostics);
        $output->writeln(json_encode($payload, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));

        $hasError = array_any($diagnostics, static fn(Diagnostic $d): bool => $d->severity === Diagnostic::SEVERITY_ERROR);

        return $hasError ? self::FAILURE : self::SUCCESS;
    }
}
