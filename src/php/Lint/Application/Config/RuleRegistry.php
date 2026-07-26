<?php

declare(strict_types=1);

namespace Phel\Lint\Application\Config;

/**
 * Stable list of all lint rule codes shipped in v1. Centralised so every
 * consumer (defaults, config loader, formatters, tests) shares one vocabulary.
 *
 * @internal
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

    public const string DUPLICATE_DEF = 'phel/duplicate-def';

    public const string INVALID_DESTRUCTURING = 'phel/invalid-destructuring';

    public const string DISCOURAGED_VAR = 'phel/discouraged-var';

    public const string COMMENT_STYLE = 'phel/comment-style';

    /**
     * Not a rule: the code `RulePipeline` reports under when a rule itself
     * throws. Deliberately absent from {@see self::allCodes()}: it has no
     * configurable severity (a crash is always an error), nothing to
     * contribute to the cache fingerprint, and keeping it out means it can
     * never be switched off from `phel-lint.phel`.
     */
    public const string INTERNAL_ERROR = 'phel/internal-error';

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
            self::DUPLICATE_DEF,
            self::INVALID_DESTRUCTURING,
            self::DISCOURAGED_VAR,
            self::COMMENT_STYLE,
        ];
    }
}
