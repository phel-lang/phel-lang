<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Evaluator\Exceptions;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\Fnable;
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
        $pattern = '/Too few arguments to function.*, (?<passed>\d+) passed in (?<tempfile>.*) on line \d+ and exactly (?<expected>\d+) expected/';
        if (preg_match($pattern, $msg, $matches)) {
            return self::toFewArgsToFunc($matches, $node, $node->getStartSourceLocation());
        }

        return $msg;
    }

    /**
     * @param array{passed:int, tempfile:string, expected:int} $matches
     */
    private static function toFewArgsToFunc(array $matches, Fnable $node, ?SourceLocation $srcLoc): string
    {
        $result = sprintf(
            'Too few arguments to function starting from `%s`, %s passed in and exactly %s expected',
            $node->getFn()->getName(),
            $matches['passed'],
            $matches['expected'],
        );

        if ($srcLoc instanceof SourceLocation && $srcLoc->getFile() !== 'string') {
            $result .= sprintf("\n> phel(location): %s:%d", $srcLoc->getFile(), $srcLoc->getLine());
        }

        $result .= sprintf(
            "\n> php(location) : %s\n\n",
            self::sanitizePhpPath($matches['tempfile']),
        );

        $phpCode = file_get_contents($matches['tempfile']);
        $returnLine = self::extractReturnLine($phpCode);
        if ($returnLine !== '') {
            $result .= $returnLine;
        } else {
            $result .= $phpCode;
        }

        return $result;
    }

    private static function sanitizePhpPath(string $fullPath): string
    {
        if (str_contains($fullPath, 'phel-lang/tests')) {
            return basename($fullPath);
        }

        return $fullPath;
    }

    private static function extractReturnLine(string $code): string
    {
        $lines = explode("\n", $code);
        foreach ($lines as $line) {
            $trimmedLine = trim($line);
            if (str_starts_with($trimmedLine, 'return')) {
                return $trimmedLine;
            }
        }

        return '';
    }
}
