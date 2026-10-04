<?php

declare(strict_types=1);

namespace Phel\Shared\SourceMap;

use Phel\Shared\VersionResolver;

use function substr_count;

final class BuiltFilePreamble
{
    private const string OPENER = '<?php declare(strict_types=1);';

    private function __construct() {}

    public static function prepend(string $phpCode): string
    {
        return self::content() . $phpCode;
    }

    /**
     * 1-based line in a built file where the generated code begins.
     */
    public static function codeStartLine(): int
    {
        return substr_count(self::content(), "\n") + 1;
    }

    /**
     * Whether the given code begins with the build preamble, marking it as a
     * `phel build` artifact. Used to tell an intentionally precompiled `.php`
     * sibling apart from an unrelated hand-written PHP file sitting next to a
     * Phel source.
     */
    public static function isPresent(string $code): bool
    {
        return str_starts_with($code, self::OPENER . "\n")
            || str_starts_with($code, self::OPENER . ' // Built with Phel ');
    }

    public static function isCurrent(string $code): bool
    {
        return str_starts_with($code, self::content());
    }

    private static function content(): string
    {
        // Keep the stamp on the opener line so existing source-map offsets stay valid.
        return self::OPENER . ' // Built with Phel ' . VersionResolver::current() . "\n";
    }
}
