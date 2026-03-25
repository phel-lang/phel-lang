<?php

declare(strict_types=1);

namespace Phel\Run\Domain\Repl;

use Phel\Compiler\Domain\Exceptions\AbstractLocatedException;
use Phel\Compiler\Domain\Parser\ReadModel\CodeSnippet;
use Phel\Shared\Facade\CommandFacadeInterface;
use Throwable;

use const PHP_EOL;

/**
 * Fallback REPL I/O when the readline extension is not available.
 * Uses fgets(STDIN) for input — no tab completion or history persistence.
 */
final readonly class ReplCommandFallbackIo implements ReplCommandIoInterface
{
    public function __construct(
        private CommandFacadeInterface $commandFacade,
    ) {}

    public function readHistory(): void {}

    public function addHistory(string $line): void {}

    public function readline(?string $prompt = null): ?string
    {
        if ($prompt !== null) {
            $this->write($prompt);
        }

        $line = fgets(STDIN);

        if ($line === false) {
            return null;
        }

        return rtrim($line, "\n\r");
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
        return false;
    }
}
