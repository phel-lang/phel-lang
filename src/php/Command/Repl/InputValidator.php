<?php

declare(strict_types=1);

namespace Phel\Command\Repl;

use RuntimeException;

final class InputValidator
{
    /**
     * @throws RuntimeException
     */
    public function isInputReadyToBeAnalyzed(array $inputBuffer): bool
    {
        //TODO: Refactor and improve this later :)
        $inputAsStr = implode(PHP_EOL, $inputBuffer);

        $totalOpenParentheses = substr_count($inputAsStr, '(');
        $totalCloseParentheses = substr_count($inputAsStr, ')');

        if ($totalCloseParentheses > $totalOpenParentheses) {
            throw new RuntimeException('Wrong number of parentheses');
        }

        $totalOpenBrackets = substr_count($inputAsStr, '[');
        $totalCloseBrackets = substr_count($inputAsStr, ']');
        if ($totalCloseBrackets > $totalOpenBrackets) {
            throw new RuntimeException('Wrong number of brackets');
        }

        $totalOpenBraces = substr_count($inputAsStr, '@{');
        $totalCloseBraces = substr_count($inputAsStr, '}');
        if ($totalCloseBraces > $totalOpenBraces) {
            throw new RuntimeException('Wrong number of braces');
        }

        return $totalOpenParentheses === $totalCloseParentheses
            && $totalOpenBrackets === $totalCloseBrackets
            && $totalOpenBraces === $totalCloseBraces;
    }
}
