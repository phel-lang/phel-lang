<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Evaluator\Exceptions;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\Fnable;
use Phel\Compiler\Domain\Analyzer\Ast\LocalVarNode;
use Phel\Lang\SourceLocation;
use RuntimeException;
use Throwable;

final class CompiledCodeIsMalformedException extends RuntimeException
{
    public static function fromThrowable(Throwable $e, AbstractNode $node): self
    {
        $msg = $e->getMessage();

        if ($node instanceof Fnable) {
            $msg = self::normalize($e->getMessage(), $node);
        }

        return new self($msg, 0, $e);
    }

    private static function normalize(string $msg, Fnable $node): string
    {
        $srcLoc = $node->getStartSourceLocation();

        $pattern = '/Too few arguments to function.*, (?<passed>\d+) passed in .* and exactly (?<expected>\d+) expected/';
        if (preg_match($pattern, $msg, $matches)) {
            /** @var LocalVarNode $fn */
            $fn = $node->getFn();
            $result = sprintf(
                'Too few arguments to function `%s`, %s passed in and exactly %s expected',
                $fn->getName(),
                $matches['passed'],
                $matches['expected'],
            );

            if ($srcLoc instanceof SourceLocation) {
                $result .= sprintf("\nlocation: %s:%d", $srcLoc->getFile(), $srcLoc->getLine());
            }

            return $result;
        }

        return $msg;
    }
}
