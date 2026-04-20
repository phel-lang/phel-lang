<?php

declare(strict_types=1);

namespace Phel\Lint\Application\Config;

/**
 * Stable list of all lint rule codes shipped in v1. Centralised so every
 * consumer (defaults, config loader, formatters, tests) shares one vocabulary.
 */
final class RuleRegistry
{
    public const string UNRESOLVED_SYMBOL = 'phel/unresolved-symbol';

    public const string ARITY_MISMATCH = 'phel/arity-mismatch';

    public const string UNUSED_BINDING = 'phel/unused-binding';

    public const string UNUSED_REQUIRE = 'phel/unused-require';

    public const string UNUSED_IMPORT = 'phel/unused-import';

    public const string SHADOWED_BINDING = 'phel/shadowed-binding';

    public const string REDUNDANT_DO = 'phel/redundant-do';

    public const string DUPLICATE_KEY = 'phel/duplicate-key';

    public const string INVALID_DESTRUCTURING = 'phel/invalid-destructuring';

    public const string DISCOURAGED_VAR = 'phel/discouraged-var';

    /**
     * @return list<string>
     */
    public static function allCodes(): array
    {
        return [
            self::UNRESOLVED_SYMBOL,
            self::ARITY_MISMATCH,
            self::UNUSED_BINDING,
            self::UNUSED_REQUIRE,
            self::UNUSED_IMPORT,
            self::SHADOWED_BINDING,
            self::REDUNDANT_DO,
            self::DUPLICATE_KEY,
            self::INVALID_DESTRUCTURING,
            self::DISCOURAGED_VAR,
        ];
    }
}
