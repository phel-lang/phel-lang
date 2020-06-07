<?php

namespace Phel\Repl;

class Readline
{
    protected $historyFile;

    public function __construct($historyFile)
    {
        $this->historyFile = $historyFile;
    }

    public function addHistory($line)
    {
        $res = readline_add_history($line);

        if ($res) {
            $this->writeHistory();
        }

        return $res;
    }

    public function clearHistory()
    {
        $res = readline_clear_history();

        if ($res) {
            $this->writeHistory();
        }

        return $res;
    }

    public function listHistory()
    {
        return readline_list_history();
    }

    public function readHistory()
    {
        readline_clear_history();
        return readline_read_history($this->historyFile);
    }

    public function readline(?string $prompt = null)
    {
        return readline($prompt);
    }

    public function redisplay()
    {
        readline_redisplay();
    }

    public function writeHistory()
    {
        return readline_write_history($this->historyFile);
    }
}
