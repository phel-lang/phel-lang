<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\ValueObject;

use InvalidArgumentException;

use function sprintf;

/**
 * Value Object representing a valid PHP identifier.
 * Ensures the identifier follows PHP naming rules.
 */
final readonly class PhpIdentifier
{
    private const string PATTERN = '/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/';

    private function __construct(
        private string $identifier,
    ) {
    }

    public static function fromString(string $identifier): self
    {
        if (!self::isValid($identifier)) {
            throw new InvalidArgumentException(
                sprintf('Invalid PHP identifier: "%s"', $identifier),
            );
        }

        return new self($identifier);
    }

    public static function tryFromString(string $identifier): ?self
    {
        if (!self::isValid($identifier)) {
            return null;
        }

        return new self($identifier);
    }

    public static function isValid(string $identifier): bool
    {
        if ($identifier === '') {
            return false;
        }

        return preg_match(self::PATTERN, $identifier) === 1;
    }

    public function toString(): string
    {
        return $this->identifier;
    }

    public function equals(self $other): bool
    {
        return $this->identifier === $other->identifier;
    }
}
