<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\ValueObject;

use function sprintf;

/**
 * Value Object representing a location in source code.
 * Encapsulates file, line, and column information.
 */
final readonly class SourceLocation
{
    private function __construct(
        private string $file,
        private int $line,
        private int $column,
    ) {
    }

    public static function create(string $file, int $line, int $column): self
    {
        return new self($file, $line, $column);
    }

    public static function unknown(): self
    {
        return new self('unknown', 0, 0);
    }

    public function file(): string
    {
        return $this->file;
    }

    public function line(): int
    {
        return $this->line;
    }

    public function column(): int
    {
        return $this->column;
    }

    public function isUnknown(): bool
    {
        return $this->file === 'unknown' && $this->line === 0 && $this->column === 0;
    }

    public function format(): string
    {
        if ($this->isUnknown()) {
            return 'unknown location';
        }

        return sprintf('%s:%d:%d', $this->file, $this->line, $this->column);
    }

    public function equals(self $other): bool
    {
        return $this->file === $other->file
            && $this->line === $other->line
            && $this->column === $other->column;
    }
}
