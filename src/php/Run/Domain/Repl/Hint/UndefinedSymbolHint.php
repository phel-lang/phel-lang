<?php

declare(strict_types=1);

namespace Phel\Run\Domain\Repl\Hint;

use Throwable;

use function preg_match;
use function sprintf;

final class UndefinedSymbolHint implements ReplHintInterface
{
    public function appliesTo(Throwable $e): bool
    {
        return $this->extract($e->getMessage()) !== null;
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
            '/Cannot resolve symbol \'?([^\']+?)\'?(?:\s|$)/',
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
