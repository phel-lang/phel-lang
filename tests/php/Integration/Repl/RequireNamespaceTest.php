<?php

declare(strict_types=1);

namespace PhelTest\Integration\Repl;

use Phel;
use Phel\Build\BuildFacade;
use Phel\Compiler\CompilerFacade;
use Phel\Compiler\Infrastructure\CompileOptions;
use Phel\Lang\Symbol;
use PHPUnit\Framework\TestCase;

final class RequireNamespaceTest extends TestCase
{
    public function test_require_loads_namespace(): void
    {
        Phel::bootstrap(__DIR__);
        Phel::addDefinition('phel\\repl', 'src-dirs', [__DIR__ . '/../../../../src']);

        $build = new BuildFacade();
        $build->evalFile(__DIR__ . '/../../../../src/phel/core.phel');
        $build->evalFile(__DIR__ . '/../../../../src/phel/repl.phel');

        $facade = new CompilerFacade();
        $result = $facade->eval('(phel\\repl/require phel\\str)', new CompileOptions());

        self::assertInstanceOf(Symbol::class, $result);
        self::assertSame('phel\\str', $result->getFullName());
    }
}
