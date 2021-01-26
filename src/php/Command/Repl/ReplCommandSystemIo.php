<?php

declare(strict_types=1);

namespace Phel\Command\Repl;

final class ReplCommandSystemIo implements ReplCommandIoInterface
{
    private string $historyFile;

    public function __construct(string $historyFile)
    {
        $this->historyFile = $historyFile;
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
