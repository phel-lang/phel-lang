<?php

declare(strict_types=1);

namespace Phel\Nrepl\Domain\Bencode;

use RuntimeException;

use function sprintf;

/**
 * @internal
 */
final class BencodeException extends RuntimeException
{
    private const int INCOMPLETE = 1;

    private const int BUFFER_LIMIT = 2;

    public static function unexpectedEndOfInput(int $position): self
    {
        return new self(sprintf('Unexpected end of input at position %d', $position), self::INCOMPLETE);
    }

    public function isIncomplete(): bool
    {
        return $this->getCode() === self::INCOMPLETE;
    }

    public static function nestingLimitExceeded(): self
    {
        return new self('Bencode nesting exceeds 64 levels');
    }

    public static function bufferLimitExceeded(): self
    {
        return new self('Bencode buffer exceeds 16 MiB', self::BUFFER_LIMIT);
    }

    public function isBufferLimitExceeded(): bool
    {
        return $this->getCode() === self::BUFFER_LIMIT;
    }

    public static function invalidToken(string $token, int $position): self
    {
        return new self(sprintf('Invalid token %s at position %d', $token, $position));
    }

    public static function invalidInteger(string $value, int $position): self
    {
        return new self(sprintf('Invalid integer "%s" at position %d', $value, $position));
    }

    public static function invalidStringLength(string $length, int $position): self
    {
        return new self(sprintf('Invalid string length "%s" at position %d', $length, $position));
    }

    public static function unsupportedType(string $type): self
    {
        return new self(sprintf('Cannot encode type %s', $type));
    }

    public static function dictKeyMustBeString(string $type): self
    {
        return new self(sprintf('Dictionary keys must be strings, got %s', $type));
    }
}
