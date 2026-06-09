<?php

declare(strict_types=1);

namespace PhelTest\Unit\Shared\SourceMap;

use Phel\Shared\SourceMap\BuiltFilePreamble;
use PHPUnit\Framework\TestCase;

use function explode;
use function substr_count;

final class BuiltFilePreambleTest extends TestCase
{
    public function test_prepend_puts_generated_code_after_php_opener(): void
    {
        $built = BuiltFilePreamble::prepend("echo 'hi';\n");

        self::assertStringStartsWith('<?php ', $built);
        self::assertStringEndsWith("\necho 'hi';\n", $built);
    }

    public function test_code_start_line_matches_prepended_layout(): void
    {
        $built = BuiltFilePreamble::prepend('generated code');

        self::assertSame(
            'generated code',
            explode("\n", $built)[BuiltFilePreamble::codeStartLine() - 1],
        );
    }

    public function test_preamble_is_a_single_line(): void
    {
        self::assertSame(1, substr_count(BuiltFilePreamble::prepend(''), "\n"));
        self::assertSame(2, BuiltFilePreamble::codeStartLine());
    }
}
