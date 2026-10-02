<?php

declare(strict_types=1);

namespace Phel\Shared\Exceptions\Hint;

use Throwable;

use function preg_match;
use function sprintf;
use function str_contains;

final class UndefinedSymbolHint implements ExceptionHintInterface
{
    public function appliesTo(Throwable $e): bool
    {
        $message = $e->getMessage();

        // The message already names the `:require` to add (#3458).
        if (str_contains($message, '(:require ')) {
            return false;
        }

        return $this->extract($message) !== null;
    }

    public function hint(Throwable $e): string
    {
        $name = $this->extract($e->getMessage()) ?? 'symbol';

        return sprintf(
            "'%s' is not defined. Check the spelling, or add (:require ...) for the namespace it lives in.",
            $name,
        );
    }

    private function extract(string $message): ?string
    {
        $patterns = [
            // A quoted name keeps its dots: `'phel.strng/join'`.
            "/Cannot resolve symbol '([^']+)'/",
            // Trailing `.` covers the analyzer's `. Did you mean ...?` suffix.
            '/Cannot resolve symbol \'?([^\']+?)\'?(?:[.\s]|$)/',
            '/Undefined (?:variable|constant|function) [\'"$]?([^\'"]+?)[\'"]?(?:\s|$|\.)/',
            '/Call to undefined function ([\\w\\\\]+)\\(\\)/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $message, $m) === 1) {
                return $m[1];
            }
        }

        return null;
    }
}
