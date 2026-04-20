<?php

declare(strict_types=1);

namespace Phel\Nrepl\Domain\Bencode;

use RuntimeException;

use function sprintf;

final class BencodeException extends RuntimeException
{
    public static function unexpectedEndOfInput(int $position): self
    {
        return new self(sprintf('Unexpected end of input at position %d', $position));
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
