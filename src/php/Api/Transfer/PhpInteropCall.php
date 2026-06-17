<?php

declare(strict_types=1);

namespace Phel\Api\Transfer;

/**
 * The interop call enclosing the cursor, resolved structurally (balanced
 * parens) rather than by a regex: the kind of call, the receiver text (a class
 * literal/alias for a constructor or static call, or a receiver expression for
 * an instance method), the method name, and the zero-based index of the
 * argument the cursor sits on.
 */
final readonly class PhpInteropCall
{
    public const string KIND_NONE = 'none';

    public const string KIND_CONSTRUCTOR = 'constructor';

    public const string KIND_METHOD = 'method';

    public function __construct(
        public string $kind,
        public string $receiver = '',
        public string $method = '',
        public int $activeParameter = 0,
    ) {}

    public static function none(): self
    {
        return new self(self::KIND_NONE);
    }

    public function isNone(): bool
    {
        return $this->kind === self::KIND_NONE;
    }
}
