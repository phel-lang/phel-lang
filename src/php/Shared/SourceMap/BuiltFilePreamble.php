<?php

declare(strict_types=1);

namespace Phel\Shared\SourceMap;

use function substr_count;

/**
 * The fixed first line(s) `phel build` writes before the generated code of
 * every compiled PHP file. The build prepends it (FileCompiler) and the error
 * printer subtracts it again to translate runtime trace lines into source-map
 * lines (SourceMapExtractor), so the layout lives here in Shared: growing the
 * preamble automatically keeps both sides in sync.
 */
final class BuiltFilePreamble
{
    private const string CONTENT = "<?php declare(strict_types=1);\n";

    private function __construct() {}

    public static function prepend(string $phpCode): string
    {
        return self::CONTENT . $phpCode;
    }

    /**
     * 1-based line in a built file where the generated code begins.
     */
    public static function codeStartLine(): int
    {
        return substr_count(self::CONTENT, "\n") + 1;
    }

    /**
     * Whether the given code begins with the build preamble, marking it as a
     * `phel build` artifact. Used to tell an intentionally precompiled `.php`
     * sibling apart from an unrelated hand-written PHP file sitting next to a
     * Phel source.
     */
    public static function isPresent(string $code): bool
    {
        return str_starts_with($code, self::CONTENT);
    }
}
