<?php

declare(strict_types=1);

namespace PhelTest\Unit\Shared\SourceMap;

use Phel\Shared\EmitterResult;
use Phel\Shared\SourceMap\InlineSourceMapComments;
use PHPUnit\Framework\TestCase;

final class InlineSourceMapCommentsTest extends TestCase
{
    public function test_ordinary_headers_keep_their_existing_bytes(): void
    {
        self::assertSame('// /src/main.phel', InlineSourceMapComments::filenameComment('/src/main.phel'));
        $result = new EmitterResult(true, 'body();', 'AAAA', '/src/main.phel');
        self::assertSame("// /src/main.phel\n// ;;AAAA\nbody();", $result->getCodeWithSourceMap());
    }

    public function test_exceptional_source_labels_round_trip_on_one_header_line(): void
    {
        foreach (["/line\nbreak.phel", "/carriage\rreturn.phel", "/both\r\nends.phel", ' leading.phel', "trailing.phel\t", '@phel-source-base64 collision', ';;collision'] as $source) {
            $comment = InlineSourceMapComments::filenameComment($source);
            self::assertStringNotContainsString("\n", $comment);
            self::assertStringNotContainsString("\r", $comment);
            self::assertSame($source, InlineSourceMapComments::parseFilenameComment($comment . "\n"));
        }
    }

    public function test_malformed_encoded_headers_and_other_comments_are_not_filenames(): void
    {
        self::assertNull(InlineSourceMapComments::parseFilenameComment('// @phel-source-base64 !invalid!'));
        self::assertNull(InlineSourceMapComments::parseFilenameComment('// @phel-source-base64 '));
        self::assertNull(InlineSourceMapComments::parseFilenameComment('// ;;AAAA'));
        self::assertNull(InlineSourceMapComments::parseFilenameComment('body();'));
    }
}
