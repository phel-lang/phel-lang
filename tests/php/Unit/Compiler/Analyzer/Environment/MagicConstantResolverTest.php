<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\Environment;

use Phel\Compiler\Domain\Analyzer\Environment\MagicConstantResolver;
use Phel\Lang\SourceLocation;
use PHPUnit\Framework\TestCase;

final class MagicConstantResolverTest extends TestCase
{
    public function test_resolve_file_returns_realpath_for_existing_file(): void
    {
        $resolver = new MagicConstantResolver();
        $sl = new SourceLocation(__FILE__, 0, 0);

        self::assertSame(__FILE__, $resolver->resolveFile($sl));
    }

    public function test_resolve_file_returns_null_when_source_location_missing(): void
    {
        $resolver = new MagicConstantResolver();

        self::assertNull($resolver->resolveFile(null));
    }

    public function test_resolve_file_returns_empty_string_for_string_source(): void
    {
        $resolver = new MagicConstantResolver();
        $sl = new SourceLocation('string', 0, 0);

        self::assertSame('', $resolver->resolveFile($sl));
    }

    public function test_resolve_file_returns_null_when_realpath_fails(): void
    {
        $resolver = new MagicConstantResolver();
        $sl = new SourceLocation('/this/path/really/should/not/exist/phel-xyz.phel', 0, 0);

        self::assertNull($resolver->resolveFile($sl));
    }

    public function test_resolve_dir_returns_realpath_dirname_for_existing_file(): void
    {
        $resolver = new MagicConstantResolver();
        $sl = new SourceLocation(__FILE__, 0, 0);

        self::assertSame(__DIR__, $resolver->resolveDir($sl));
    }

    public function test_resolve_dir_returns_null_when_source_location_missing(): void
    {
        $resolver = new MagicConstantResolver();

        self::assertNull($resolver->resolveDir(null));
    }

    public function test_resolve_dir_returns_empty_string_for_string_source(): void
    {
        $resolver = new MagicConstantResolver();
        $sl = new SourceLocation('string', 0, 0);

        self::assertSame('', $resolver->resolveDir($sl));
    }

    public function test_resolve_dir_returns_null_when_realpath_fails(): void
    {
        $resolver = new MagicConstantResolver();
        $sl = new SourceLocation('/this/path/really/should/not/exist/phel-xyz.phel', 0, 0);

        self::assertNull($resolver->resolveDir($sl));
    }
}
