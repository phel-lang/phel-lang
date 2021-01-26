<?php

declare(strict_types=1);

namespace PhelTest\Integration\Repl;

final class InputLine
{
    private string $prompt;
    private string $content;

    public function __construct(string $prompt, string $content)
    {
        $this->prompt = $prompt;
        $this->content = $content;
    }

    public function getContent() {
        return $this->content;
    }

    public function isCtrlD() {
        return $this->content === '<CTRL-D>';
    }

    public function __toString()
    {
        return $this->prompt . $this->content;
    }
}
