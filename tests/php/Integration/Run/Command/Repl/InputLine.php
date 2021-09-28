<?php

declare(strict_types=1);

namespace PhelTest\Integration\Run\Command\Repl;

final class InputLine
{
    private string $prompt;
    private string $content;

    public function __construct(string $prompt, string $content)
    {
        $this->prompt = $prompt;
        $this->content = $content;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function isCtrlD(): bool
    {
        return $this->content === '<CTRL-D>';
    }

    public function __toString(): string
    {
        return $this->prompt . $this->content;
    }
}
