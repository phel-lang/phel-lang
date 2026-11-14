<?php

declare(strict_types=1);

namespace Phel\Shared\SourceMap;

use function strlen;

/**
 * Comment prefixes for the inline source-map metadata embedded in eval'd
 * code: a `// <source>` filename comment followed by a `// ;;<mappings>`
 * comment. The emitter writes them (EmitterResult) and the error printers
 * parse them back (SourceMapExtractor, EvaluatedCodeException), so the
 * convention lives here in Shared.
 *
 * Note that FILENAME_PREFIX is a prefix of MAPPINGS_PREFIX, so readers must
 * check for the mappings prefix first.
 */
final class InlineSourceMapComments
{
    public const string FILENAME_PREFIX = '// ';

    public const string MAPPINGS_PREFIX = '// ;;';

    private const string ENCODED_FILENAME_PREFIX = '// @phel-source-base64 ';

    private function __construct() {}

    public static function filenameComment(string $source): string
    {
        $comment = self::FILENAME_PREFIX . $source;
        if (str_contains($source, "\r") || str_contains($source, "\n")
            || trim($source) !== $source
            || str_starts_with($comment, self::ENCODED_FILENAME_PREFIX)
            || str_starts_with($comment, self::MAPPINGS_PREFIX)
        ) {
            return self::ENCODED_FILENAME_PREFIX . base64_encode($source);
        }

        return $comment;
    }

    public static function parseFilenameComment(string $comment): ?string
    {
        $comment = rtrim($comment, "\r\n");
        if (str_starts_with($comment, self::ENCODED_FILENAME_PREFIX)) {
            $source = base64_decode(substr($comment, strlen(self::ENCODED_FILENAME_PREFIX)), true);

            return $source === false || $source === '' ? null : $source;
        }

        if (!str_starts_with($comment, self::FILENAME_PREFIX)
            || str_starts_with($comment, self::MAPPINGS_PREFIX)
        ) {
            return null;
        }

        return trim(substr($comment, strlen(self::FILENAME_PREFIX)));
    }
}
