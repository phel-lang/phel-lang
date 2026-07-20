<?php

declare(strict_types=1);

namespace Phel\Run\Domain\Repl;

use Phel\Shared\Exceptions\AbstractLocatedException;
use Phel\Shared\Parser\ReadModel\CodeSnippet;
use Throwable;

use const PHP_EOL;

/**
 * Shared output half of {@see ReplCommandIoInterface}: error rendering and
 * plain stdout writing. Composing classes must provide the private
 * `$commandFacade` ({@see \Phel\Shared\Facade\CommandFacadeInterface}) and
 * `$errorFormatter` ({@see ReplErrorFormatter}) properties.
 */
trait ReplOutputTrait
{
    public function writeStackTrace(Throwable $e): void
    {
        $this->writeln($this->commandFacade->getStackTraceString($e));
    }

    public function writeReplError(Throwable $e): void
    {
        $this->writeln($this->errorFormatter->render($e));
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
}
