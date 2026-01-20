<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\ValueObject;

use InvalidArgumentException;

use function sprintf;

/**
 * Value Object representing Phel source code to be compiled.
 * Encapsulates the source code string with validation.
 */
final readonly class PhelSourceCode
{
    private function __construct(
        private string $code,
        private string $sourcePath,
    ) {
    }

    public static function fromString(string $code, string $sourcePath = 'string'): self
    {
        return new self($code, $sourcePath);
    }

    public static function fromFile(string $filePath): self
    {
        if (!file_exists($filePath)) {
            throw new InvalidArgumentException(
                sprintf('File not found: "%s"', $filePath),
            );
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new InvalidArgumentException(
                sprintf('Could not read file: "%s"', $filePath),
            );
        }

        return new self($content, $filePath);
    }

    public function code(): string
    {
        return $this->code;
    }

    public function sourcePath(): string
    {
        return $this->sourcePath;
    }

    public function isEmpty(): bool
    {
        return trim($this->code) === '';
    }

    public function lineCount(): int
    {
        return substr_count($this->code, "\n") + 1;
    }
}
