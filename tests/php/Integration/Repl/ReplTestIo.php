<?php

declare(strict_types=1);

namespace PhelTest\Integration\Repl;

use Phel\Command\Repl\ReplCommandIoInterface;

final class ReplTestIo implements ReplCommandIoInterface
{
    private $outputs = [];
    private $inputs = [];
    private $currentIndex = 0;

    public function readHistory(): void
    {
    }

    public function addHistory(string $line): void
    {
    }

    public function readline(?string $prompt = null): ?string
    {
        if ($this->currentIndex < count($this->inputs)) {
            $line = $this->inputs[$this->currentIndex];
            $this->output('>>> ' . $line . "\n");
            $this->currentIndex++;

            return $line;
        }

        return null;
    }

    public function output(string $string): void
    {
        $this->outputs[] = $string;
    }

    public function setInputs(array $inputs): void
    {
        $this->inputs = $inputs;
        $this->currentIndex = 0;
    }

    public function getOutputs()
    {
        return array_slice($this->outputs, 2, -1);
    }

    public function getOutputString()
    {
        return implode('', $this->getOutputs());
    }

    public function isBracketedPasteSupported(): bool
    {
        return false;
    }
}
