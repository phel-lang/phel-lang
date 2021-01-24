<?php

declare(strict_types=1);

namespace Phel\Command\Repl;

use RuntimeException;

final class InputValidator
{
    private int $totalOpenRoundBrackets = 0;
    private int $totalCloseRoundBrackets = 0;
    private int $totalOpenSquareBrackets = 0;
    private int $totalCloseSquareBrackets = 0;
    private int $totalOpenCurlyBrackets = 0;
    private int $totalCloseCurlyBrackets = 0;
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

        $this->validateNumberOfBrackets();

        return !$this->isStringContext()
            && $this->areAllBracketsClosed();
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
                $this->totalOpenRoundBrackets++;
                break;
            case ')':
                $this->totalCloseRoundBrackets++;
                break;
            case '[':
                $this->totalOpenSquareBrackets++;
                break;
            case ']':
                $this->totalCloseSquareBrackets++;
                break;
            case '{':
                $this->totalOpenCurlyBrackets++;
                break;
            case '}':
                $this->totalCloseCurlyBrackets++;
                break;
        }
    }

    /**
     * @throws RuntimeException
     */
    private function validateNumberOfBrackets(): void
    {
        if ($this->totalCloseRoundBrackets > $this->totalOpenRoundBrackets) {
            throw new RuntimeException('Wrong number of parentheses');
        }

        if ($this->totalCloseSquareBrackets > $this->totalOpenSquareBrackets) {
            throw new RuntimeException('Wrong number of brackets');
        }

        if ($this->totalCloseCurlyBrackets > $this->totalOpenCurlyBrackets) {
            throw new RuntimeException('Wrong number of braces');
        }
    }

    private function areAllBracketsClosed(): bool
    {
        return $this->totalOpenRoundBrackets === $this->totalCloseRoundBrackets
            && $this->totalOpenSquareBrackets === $this->totalCloseSquareBrackets
            && $this->totalOpenCurlyBrackets === $this->totalCloseCurlyBrackets;
    }
}
