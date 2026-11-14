<?php

declare(strict_types=1);

namespace PhelTest\Unit\Shared\SourceMap;

use Phel\Shared\SourceMap\SourcePathMarker;
use PHPUnit\Framework\TestCase;

final class SourcePathMarkerTest extends TestCase
{
    public function test_source_paths_and_directory_flags_round_trip(): void
    {
        foreach (['', '/src/main.phel', "/line\r\nbreak/λ.phel"] as $file) {
            foreach ([false, true] as $directory) {
                self::assertSame(['file' => $file, 'directory' => $directory], SourcePathMarker::decode(SourcePathMarker::encode($file, $directory)));
            }
        }
    }

    public function test_other_comments_are_not_source_paths(): void
    {
        self::assertNull(SourcePathMarker::decode('/* ordinary comment */'));
    }
}
