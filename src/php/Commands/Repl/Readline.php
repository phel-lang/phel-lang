<?php

declare(strict_types=1);

namespace Phel\Commands\Repl;

final class Readline
{
    private string $historyFile;

    public function __construct(string $historyFile)
    {
        $this->historyFile = $historyFile;
    }

    public function addHistory(string $line): bool
    {
        $res = readline_add_history($line);

        if ($res) {
            $this->writeHistory();
        }

        return $res;
    }

    public function clearHistory(): bool
    {
        $res = readline_clear_history();

        if ($res) {
            $this->writeHistory();
        }

        return $res;
    }

    public function listHistory(): array
    {
        return readline_list_history();
    }

    public function readHistory(): bool
    {
        readline_clear_history();
        return readline_read_history($this->historyFile);
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

    public function redisplay(): void
    {
        readline_redisplay();
    }

    public function writeHistory(): bool
    {
        return readline_write_history($this->historyFile);
    }
}
