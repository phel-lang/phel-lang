<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\Resolver;

use Generator;
use InvalidArgumentException;
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
    public function test_it_resolves_paths_relative_to_caller_namespace(
        string $callerNamespace,
        string $pathArg,
        string $expectedLoadKey,
        string $expectedCallerDir,
    ): void {
        $resolution = $this->resolver->resolve($callerNamespace, $pathArg);

        self::assertFalse($resolution->isClasspathAbsolute());
        self::assertSame($expectedLoadKey, $resolution->loadKey);
        self::assertSame($expectedCallerDir, $resolution->callerClasspathDir);
    }

    public static function provideRelativeCases(): Generator
    {
        yield 'sibling file' => ['phel\\core', 'extras', 'extras', 'phel'];
        yield 'nested relative path' => ['phel\\core', 'core/extras', 'core/extras', 'phel'];
        yield 'caller in nested namespace' => ['phel\\http\\server', 'handler', 'handler', 'phel/http'];
        yield 'top-level caller namespace' => ['app', 'helper', 'helper', ''];
    }

    #[DataProvider('provideClasspathAbsoluteCases')]
    public function test_it_marks_leading_slash_as_classpath_absolute(
        string $pathArg,
        string $expectedLoadKey,
    ): void {
        $resolution = $this->resolver->resolve('any\\ns', $pathArg);

        self::assertTrue($resolution->isClasspathAbsolute());
        self::assertSame($expectedLoadKey, $resolution->loadKey);
        self::assertSame('', $resolution->callerClasspathDir);
    }

    public static function provideClasspathAbsoluteCases(): Generator
    {
        yield 'single segment' => ['/string', 'string'];
        yield 'nested segments' => ['/phel/string', 'phel/string'];
    }

    public function test_it_rejects_relative_path_without_caller_namespace(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('no caller namespace available');

        $this->resolver->resolve(null, 'helper');
    }

    #[DataProvider('provideInvalidPathCases')]
    public function test_it_rejects_invalid_paths(string $pathArg, string $expectedMessage): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessage);

        $this->resolver->resolve('any\\ns', $pathArg);
    }

    public static function provideInvalidPathCases(): Generator
    {
        yield 'empty path' => ['', 'must not be empty'];
        yield 'explicit .phel extension' => ['core/extras.phel', 'must not include'];
        yield 'relative dot-slash prefix' => ['./helper', "must not start with './'"];
        yield 'relative parent-slash prefix' => ['../helper', "must not start with './'"];
    }
}
