<?php

declare(strict_types=1);

namespace Phel\Api\Transfer;

/**
 * The PHP-interop completion context resolved at a cursor position: which kind
 * of interop is being typed, the receiver class (for member access), and the
 * partial token already typed.
 */
final readonly class PhpInteropContext
{
    public const string KIND_NONE = 'none';

    public const string KIND_INSTANCE_MEMBER = 'instance-member';

    public const string KIND_STATIC_MEMBER = 'static-member';

    public const string KIND_CLASS_NAME = 'class-name';

    public const string KIND_GLOBAL_FUNCTION = 'global-function';

    public function __construct(
        public string $kind,
        public string $prefix = '',
        public string $class = '',
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
