<?php

declare(strict_types=1);

namespace Phel\Run\Domain\Repl;

use Phel\Command\CommandFacadeInterface;
use Phel\Compiler\Exceptions\AbstractLocatedException;
use Phel\Compiler\Parser\ReadModel\CodeSnippet;
use Throwable;

final class ReplCommandSystemIo implements ReplCommandIoInterface
{
    private string $historyFile;
    private CommandFacadeInterface $commandFacade;

    public function __construct(
        string $historyFile,
        CommandFacadeInterface $commandFacade
    ) {
        $this->historyFile = $historyFile;
        $this->commandFacade = $commandFacade;
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
        print $string;
    }

    public function writeln(string $string = ''): void
    {
        print $string . PHP_EOL;
    }

    public function isBracketedPasteSupported(): bool
    {
        return \stripos(\readline_info('library_version'), 'editline') === false;
    }
}
