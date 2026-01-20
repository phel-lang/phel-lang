<?php

declare(strict_types=1);

namespace Phel\Build\Domain\ValueObject;

use InvalidArgumentException;

use function dirname;
use function strlen;

/**
 * Value Object representing a file path in the Phel build system.
 * Encapsulates path validation, normalization, and common operations.
 */
final readonly class PhelFilePath
{
    private function __construct(
        private string $path,
    ) {
    }

    public static function fromString(string $path): self
    {
        if ($path === '') {
            throw new InvalidArgumentException('File path cannot be empty');
        }

        return new self($path);
    }

    public function toString(): string
    {
        return $this->path;
    }

    public function getDirectory(): self
    {
        return new self(dirname($this->path));
    }

    public function getFilename(): string
    {
        return basename($this->path);
    }

    public function getExtension(): string
    {
        return pathinfo($this->path, PATHINFO_EXTENSION);
    }

    public function withExtension(string $extension): self
    {
        $info = pathinfo($this->path);
        $dir = $info['dirname'] ?? '';
        $filename = $info['filename'];

        return new self($dir . DIRECTORY_SEPARATOR . $filename . '.' . ltrim($extension, '.'));
    }

    public function containsPath(string $segment): bool
    {
        return str_contains($this->path, $segment);
    }

    public function isWithinDirectory(string $directory): bool
    {
        return str_starts_with($this->path, rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR);
    }

    public function relativeTo(string $basePath): self
    {
        $basePath = rtrim($basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        if (str_starts_with($this->path, $basePath)) {
            return new self(substr($this->path, strlen($basePath)));
        }

        return $this;
    }

    public function join(string $segment): self
    {
        return new self($this->path . DIRECTORY_SEPARATOR . ltrim($segment, DIRECTORY_SEPARATOR));
    }

    public function isPhelFile(): bool
    {
        return $this->getExtension() === 'phel';
    }

    public function isPhpFile(): bool
    {
        return $this->getExtension() === 'php';
    }

    public function equals(self $other): bool
    {
        return $this->path === $other->path;
    }
}
