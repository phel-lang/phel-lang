<?php

declare(strict_types=1);

namespace Phel\Shared;

/**
 * The lint rule codes, and part of the public API.
 *
 * `phel lint --format=json` writes these strings into its `code` field, so they
 * are a contract with whatever reads that output, not an implementation detail.
 * Editor integrations already match on them. Renaming one is a breaking change.
 *
 * Living under `Phel\Shared` is what makes that promise: rule 4 of the public
 * API in `docs/stability.md` covers the whole namespace, and
 * `PublicApiSurfaceTest` gates it, so a rename fails CI instead of silently
 * breaking a consumer.
 *
 * @see Exceptions\ErrorCode for the PHEL0xx and PHEL4xx codes,
 *      which are public for the same reason: `phel explain <code>` tells people
 *      to type them.
 */
final class LintRuleCodes
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
