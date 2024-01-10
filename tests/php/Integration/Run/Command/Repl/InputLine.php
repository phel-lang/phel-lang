<?php

declare(strict_types=1);

namespace PhelTest\Integration\Run\Command\Repl;

use Stringable;

final readonly class InputLine implements Stringable
{
    public function __construct(
        private string $prompt,
        private string $content,
    ) {
    }

    public function __toString(): string
    {
        return $this->prompt . $this->content;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function isCtrlD(): bool
    {
        return $this->content === '<CTRL-D>';
    }
}
