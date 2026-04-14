<?php

declare(strict_types=1);

namespace PhelTest\Integration\Repl;

use Phel;
use Phel\Build\BuildFacade;
use Phel\Compiler\CompilerFacade;
use Phel\Compiler\Infrastructure\CompileOptions;
use Phel\Lang\Symbol;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RequireNamespaceTest extends TestCase
{
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_require_loads_namespace(): void
    {
        Phel::bootstrap(__DIR__);
        Phel::addDefinition('phel\\repl', 'src-dirs', [__DIR__ . '/../../../../src']);

        $srcDir = __DIR__ . '/../../../../src';
        $build = new BuildFacade();
        $deps = $build->getDependenciesForNamespace([$srcDir], ['phel\\repl']);
        foreach ($deps as $dep) {
            $build->evalFile($dep->getFile());
        }

        $facade = new CompilerFacade();
        $result = $facade->eval('(phel\\repl/require phel\\string)', new CompileOptions());

        self::assertInstanceOf(Symbol::class, $result);
        self::assertSame('phel\\string', $result->getFullName());
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_require_nonexistent_namespace_throws(): void
    {
        Phel::bootstrap(__DIR__);
        Phel::addDefinition('phel\\repl', 'src-dirs', [__DIR__ . '/../../../../src']);

        $srcDir = __DIR__ . '/../../../../src';
        $build = new BuildFacade();
        $deps = $build->getDependenciesForNamespace([$srcDir], ['phel\\repl']);
        foreach ($deps as $dep) {
            $build->evalFile($dep->getFile());
        }

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Could not locate namespace 'nonexistent\\foo'");

        $facade = new CompilerFacade();
        $facade->eval('(phel\\repl/require nonexistent\\foo)', new CompileOptions());
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_require_with_dot_separator(): void
    {
        Phel::bootstrap(__DIR__);
        Phel::addDefinition('phel\\repl', 'src-dirs', [__DIR__ . '/../../../../src']);

        $srcDir = __DIR__ . '/../../../../src';
        $build = new BuildFacade();
        $deps = $build->getDependenciesForNamespace([$srcDir], ['phel\\repl']);
        foreach ($deps as $dep) {
            $build->evalFile($dep->getFile());
        }

        $facade = new CompilerFacade();
        $result = $facade->eval('(phel\\repl/require phel.string)', new CompileOptions());

        self::assertInstanceOf(Symbol::class, $result);
        self::assertSame('phel\\string', $result->getFullName());
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_require_with_clojure_alias(): void
    {
        Phel::bootstrap(__DIR__);
        Phel::addDefinition('phel\\repl', 'src-dirs', [__DIR__ . '/../../../../src']);

        $srcDir = __DIR__ . '/../../../../src';
        $build = new BuildFacade();
        $deps = $build->getDependenciesForNamespace([$srcDir], ['phel\\repl']);
        foreach ($deps as $dep) {
            $build->evalFile($dep->getFile());
        }

        $facade = new CompilerFacade();
        $result = $facade->eval('(phel\\repl/require clojure.string)', new CompileOptions());

        self::assertInstanceOf(Symbol::class, $result);
        self::assertSame('phel\\string', $result->getFullName());
    }
}
