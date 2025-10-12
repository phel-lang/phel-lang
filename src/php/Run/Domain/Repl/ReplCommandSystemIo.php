<?php

declare(strict_types=1);

namespace Phel\Run\Domain\Repl;

use Phel\Compiler\Domain\Exceptions\AbstractLocatedException;
use Phel\Compiler\Domain\Parser\ReadModel\CodeSnippet;
use Phel\Shared\Facade\ApiFacadeInterface;
use Phel\Shared\Facade\CommandFacadeInterface;
use Throwable;

use function extension_loaded;

final readonly class ReplCommandSystemIo implements ReplCommandIoInterface
{
    public function __construct(
        private string $historyFile,
        private CommandFacadeInterface $commandFacade,
        private ApiFacadeInterface $apiFacade,
    ) {
        if (!extension_loaded('readline')) {
            throw MissingDependencyException::missingExtension('readline');
        }

        readline_completion_function(
            fn (string $input): array => $this->apiFacade->replComplete($input),
        );
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

    /**
     * @psalm-taint-escape html
     * @psalm-taint-escape has_quotes
     *
     * @psalm-suppress TaintedHtml
     * @psalm-suppress TaintedTextWithQuotes
     */
    public function write(string $string = ''): void
    {
        /** @psalm-suppress TaintedHtml */
        /** @psalm-suppress TaintedTextWithQuotes */
        echo $string; // phpcs:ignore
    }

    /**
     * @psalm-taint-escape html
     * @psalm-taint-escape has_quotes
     *
     * @psalm-suppress TaintedHtml
     * @psalm-suppress TaintedTextWithQuotes
     */
    public function writeln(string $string = ''): void
    {
        /** @psalm-suppress TaintedHtml */
        /** @psalm-suppress TaintedTextWithQuotes */
        echo $string . PHP_EOL; // phpcs:ignore
    }

    public function isBracketedPasteSupported(): bool
    {
        $haystack = readline_info('library_version') ?? '';

        return stripos($haystack, 'editline') === false;
    }

}
