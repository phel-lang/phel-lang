<?php

declare(strict_types=1);

namespace Phel\Command\Repl;

use RuntimeException;

final class InputValidator
{
    private int $totalOpenParentheses = 0;
    private int $totalCloseParentheses = 0;
    private int $totalDoubleQuotes = 0;

    /**
     * @param string[] $inputBuffer
     *
     * @throws RuntimeException
     */
    public function isInputReadyToBeAnalyzed(array $inputBuffer): bool
    {
        foreach ($inputBuffer as $line) {
            for ($i = 0, $totalChars = strlen($line); $i < $totalChars; $i++) {
                $char = $line[$i];

                if ($this->shouldStopReadingLine($char)) {
                    break;
                }

                if ($this->startOrEndStringContext($char, $line[$i - 1])) {
                    $this->totalDoubleQuotes++;
                }

                $this->readParentheses($char);
            }
        }

        return !$this->isStringContext()
            && $this->areParenthesesClosed();
    }

    private function shouldStopReadingLine(string $char): bool
    {
        return '#' === $char && !$this->isStringContext();
    }

    private function isStringContext(): bool
    {
        return $this->totalDoubleQuotes % 2 !== 0;
    }

    private function startOrEndStringContext(string $char, string $prevChar): bool
    {
        return '"' === $char && $prevChar !== '\\';
    }

    private function readParentheses(string $char): void
    {
        switch ($char) {
            case '(':
                $this->totalOpenParentheses++;
                break;
            case ')':
                $this->totalCloseParentheses++;
                break;
        }
    }

    private function areParenthesesClosed(): bool
    {
        return $this->totalCloseParentheses >= $this->totalOpenParentheses;
    }
}
