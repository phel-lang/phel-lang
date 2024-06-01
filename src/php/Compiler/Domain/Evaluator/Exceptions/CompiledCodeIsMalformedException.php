<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Evaluator\Exceptions;

use RuntimeException;
use Throwable;

final class CompiledCodeIsMalformedException extends RuntimeException
{
    public static function fromThrowable(Throwable $e): self
    {
        $msg = self::normalize($e->getMessage());

        return new self($msg, 0, $e);
    }

    private static function normalize(string $msg): string
    {
        $pattern = '/Too few arguments to function [^,]+, (\d+) passed in [^,]+ on line (\d+) and exactly (\d+) expected/';
        if (preg_match($pattern, $msg, $matches)) {
            return sprintf(
                'Too few arguments to function on line %s, %s passed in and exactly %s expected',
                $matches[2],
                $matches[1],
                $matches[3],
            );
        }

        return $msg;
    }
}
