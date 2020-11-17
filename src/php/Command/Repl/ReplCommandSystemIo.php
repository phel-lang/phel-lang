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

    public function output(string $string): void
    {
        fwrite(STDOUT, $string);
    }
}
