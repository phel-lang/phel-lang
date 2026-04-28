<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Evaluator\Exceptions;

use Phel\Compiler\Domain\Emitter\OutputEmitter\SourceMap\SourceMapConsumer;
use RuntimeException;
use Throwable;

use function explode;
use function str_starts_with;
use function substr;
use function trim;

/**
 * Wraps a Throwable raised while running compiled Phel code via eval().
 * Carries the original Phel source location resolved through the embedded source map,
 * so error reporting can point to the user's `.phel` line instead of the eval'd code.
 */
final class EvaluatedCodeException extends RuntimeException
{
    private function __construct(
        Throwable $original,
        private readonly string $phelFile,
        private readonly int $phelLine,
    ) {
        parent::__construct($original->getMessage(), 0, $original);
    }

    public static function fromThrowableAndCompiledCode(
        Throwable $original,
        string $compiledCode,
        int $headerOffset = 0,
    ): self {
        [$file, $line] = self::extractSourceLocation($compiledCode, $original->getLine() - $headerOffset);

        return new self($original, $file, $line);
    }

    public function getPhelFile(): string
    {
        return $this->phelFile;
    }

    public function getPhelLine(): int
    {
        return $this->phelLine;
    }

    public function getOriginalException(): Throwable
    {
        $previous = $this->getPrevious();

        return $previous ?? $this;
    }

    /**
     * @return array{0: string, 1: int}
     */
    private static function extractSourceLocation(string $compiledCode, int $generatedLine): array
    {
        $lines = explode("\n", $compiledCode, 3);
        $filenameComment = $lines[0];
        $sourceMapComment = $lines[1] ?? '';

        $file = str_starts_with($filenameComment, '// ')
            ? trim(substr($filenameComment, 3))
            : 'string';

        if (!str_starts_with($sourceMapComment, '// ;;')) {
            return [$file, $generatedLine];
        }

        $mapping = trim(substr($sourceMapComment, 3));
        if ($mapping === '' || $mapping === ';;') {
            return [$file, $generatedLine];
        }

        $consumer = new SourceMapConsumer($mapping);
        $originalLine = $consumer->getOriginalLine($generatedLine);

        return [$file, $originalLine ?? $generatedLine];
    }
}
