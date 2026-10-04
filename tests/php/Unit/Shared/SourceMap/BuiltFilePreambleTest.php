<?php

declare(strict_types=1);

namespace PhelTest\Unit\Shared\SourceMap;

use Phel\Shared\SourceMap\BuiltFilePreamble;
use Phel\Shared\VersionResolver;
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
        self::assertStringContainsString('// Built with Phel ' . new VersionResolver()->resolve() . "\n", $built);
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

    public function test_recognizes_stamped_and_legacy_builds_independently_of_the_runtime_version(): void
    {
        self::assertTrue(BuiltFilePreamble::isPresent(BuiltFilePreamble::prepend('')));
        self::assertTrue(BuiltFilePreamble::isPresent("<?php declare(strict_types=1); // Built with Phel v0.1.0\n"));
        self::assertTrue(BuiltFilePreamble::isPresent("<?php declare(strict_types=1);\n"));
        self::assertFalse(BuiltFilePreamble::isPresent("<?php\necho 'hi';\n"));
    }

    public function test_only_current_stamped_builds_are_safe_to_reuse(): void
    {
        self::assertTrue(BuiltFilePreamble::isCurrent(BuiltFilePreamble::prepend('')));
        self::assertFalse(BuiltFilePreamble::isCurrent("<?php declare(strict_types=1); // Built with Phel v0.0.0\n"));
        self::assertFalse(BuiltFilePreamble::isCurrent("<?php declare(strict_types=1);\n"));
    }
}
