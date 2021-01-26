<?php

declare(strict_types=1);

namespace PhelTest\Integration\Repl;

use Phel\Command\Repl\ReplCommandIoInterface;

final class ReplTestIo implements ReplCommandIoInterface
{
    private array $outputs = [];
    private array $inputs = [];
    private int $currentIndex = 0;

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
            $this->currentIndex++;

            return $line;
        }

        return null;
    }

    public function write(string $string = ''): void
    {
        $this->outputs[] = $string;
    }

    public function writeln(string $string = ''): void
    {
        $this->outputs[] = $string;
    }

    public function setInputs(array $inputs): void
    {
        $this->inputs = $inputs;
        $this->currentIndex = 0;
    }

    public function getOutputs(): array
    {
        return array_slice($this->outputs, 2, -1);
    }

    public function getOutputString(): string
    {
        return implode('', $this->getOutputs()) . PHP_EOL;
    }

    public function isBracketedPasteSupported(): bool
    {
        return false;
    }
}
