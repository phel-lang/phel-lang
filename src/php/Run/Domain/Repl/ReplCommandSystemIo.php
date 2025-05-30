<?php

declare(strict_types=1);

namespace Phel\Run\Domain\Repl;

use Phel\Command\CommandFacadeInterface;
use Phel\Compiler\Domain\Exceptions\AbstractLocatedException;
use Phel\Compiler\Domain\Parser\ReadModel\CodeSnippet;
use Phel\Lang\FnInterface;
use Phel\Lang\Registry;
use Throwable;

use function extension_loaded;
use function sprintf;
use function str_starts_with;

final readonly class ReplCommandSystemIo implements ReplCommandIoInterface
{
    public function __construct(
        private string $historyFile,
        private CommandFacadeInterface $commandFacade,
    ) {
        if (!extension_loaded('readline')) {
            throw MissingDependencyException::missingExtension('readline');
        }

        readline_completion_function(fn (string $input, int $index): array => $this->completion($input));
    }

    public function readHistory(): void
    {
        readline_clear_history();
        readline_read_history($this->historyFile);
    }

    public function addHistory(string $line): void
    {
        readline_add_history($line);
        readline_write_history($this->historyFile);
    }

    public function readline(?string $prompt = null): ?string
    {
        /** @var false|string $line */
        $line = readline($prompt);

        if ($line === false) {
            return null;
        }

        return $line;
    }

    public function writeStackTrace(Throwable $e): void
    {
        $this->writeln($this->commandFacade->getStackTraceString($e));
    }

    public function writeLocatedException(AbstractLocatedException $e, CodeSnippet $codeSnippet): void
    {
        $this->writeln($this->commandFacade->getExceptionString($e, $codeSnippet));
    }

    public function write(string $string = ''): void
    {
        echo $string;
    }

    public function writeln(string $string = ''): void
    {
        echo $string . PHP_EOL;
    }

    public function isBracketedPasteSupported(): bool
    {
        $haystack = readline_info('library_version') ?? '';

        return stripos($haystack, 'editline') === false;
    }

    /**
     * Get a sorted list of function names that start with the given input.
     *
     * @return list<string>
     */
    private function completion(string $input): array
    {
        $registry = Registry::getInstance();
        $namespaces = $registry->getNamespaces();

        $functions = [];
        foreach ($namespaces as $namespace) {
            $definitions = $registry->getDefinitionInNamespace($namespace);

            foreach ($definitions as $name => $fn) {
                if ($fn instanceof FnInterface) {
                    $functions[] = ($namespace === 'phel\\core')
                        ? $name
                        : sprintf('%s\%s', $namespace, $name);
                }
            }
        }

        if (trim($input) === '') {
            return [];
        }

        $matches = array_filter(
            $functions,
            static fn (string $function): bool => str_starts_with($function, $input),
        );

        sort($matches);

        return $matches;
    }
}
