<?php

declare(strict_types=1);

namespace Phel\Shared\SourceMap;

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

    private function __construct() {}
}
