<?php

declare(strict_types=1);

namespace Phel\Run\Domain\Repl\Hint;

use ArgumentCountError;
use Throwable;

use function preg_match;
use function sprintf;

final class ArgumentCountHint implements ReplHintInterface
{
    public function appliesTo(Throwable $e): bool
    {
        return $e instanceof ArgumentCountError;
    }

    public function hint(Throwable $e): string
    {
        $message = $e->getMessage();

        if (preg_match('/(\d+) passed.*?and (?:exactly |at least )?(\d+).*?expected/', $message, $m) === 1) {
            $given = (int) $m[1];
            $expected = (int) $m[2];

            return sprintf(
                'wrong arity: expected %d argument%s, got %d.',
                $expected,
                $expected === 1 ? '' : 's',
                $given,
            );
        }

        return 'wrong number of arguments passed.';
    }
}
