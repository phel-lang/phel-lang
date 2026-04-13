<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\Resolver;

use Generator;
use InvalidArgumentException;
use Phel\Compiler\Domain\Analyzer\Resolver\LoadPathResolution;
use Phel\Compiler\Domain\Analyzer\Resolver\LoadPathResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LoadPathResolverTest extends TestCase
{
    private LoadPathResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new LoadPathResolver();
    }

    #[DataProvider('provideRelativeCases')]
    public function test_it_resolves_paths_relative_to_caller_file_directory(
        string $callerFile,
        string $pathArg,
        string $expectedAbsolute,
    ): void {
        $resolution = $this->resolver->resolve($callerFile, $pathArg);

        self::assertFalse($resolution->isClasspathAbsolute());
        self::assertSame(LoadPathResolution::MODE_FILESYSTEM, $resolution->mode);
        self::assertSame($expectedAbsolute, $resolution->path);
    }

    public static function provideRelativeCases(): Generator
    {
        yield 'sibling file' => [
            '/src/phel/core.phel', 'extras', '/src/phel/extras.phel',
        ];

        yield 'nested relative path' => [
            '/src/phel/core.phel', 'core/extras', '/src/phel/core/extras.phel',
        ];

        yield 'deeper caller file' => [
            '/src/phel/core/extras.phel', 'helper', '/src/phel/core/helper.phel',
        ];
    }

    #[DataProvider('provideClasspathAbsoluteCases')]
    public function test_it_marks_leading_slash_as_classpath_absolute(
        string $pathArg,
        string $expectedRelative,
    ): void {
        $resolution = $this->resolver->resolve('/any/file.phel', $pathArg);

        self::assertTrue($resolution->isClasspathAbsolute());
        self::assertSame(LoadPathResolution::MODE_CLASSPATH_ABSOLUTE, $resolution->mode);
        self::assertSame($expectedRelative, $resolution->path);
    }

    public static function provideClasspathAbsoluteCases(): Generator
    {
        yield 'single segment' => ['/str', 'str.phel'];
        yield 'nested segments' => ['/phel/str', 'phel/str.phel'];
    }

    public function test_it_rejects_relative_path_without_caller_file(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('no caller source location available');

        $this->resolver->resolve(null, 'helper');
    }

    #[DataProvider('provideInvalidPathCases')]
    public function test_it_rejects_invalid_paths(string $pathArg, string $expectedMessage): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessage);

        $this->resolver->resolve('/any/file.phel', $pathArg);
    }

    public static function provideInvalidPathCases(): Generator
    {
        yield 'empty path' => ['', 'must not be empty'];
        yield 'explicit .phel extension' => ['core/extras.phel', 'must not include'];
        yield 'relative dot-slash prefix' => ['./helper', "must not start with './'"];
        yield 'relative parent-slash prefix' => ['../helper', "must not start with './'"];
    }
}
