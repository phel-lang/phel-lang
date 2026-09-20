<?php

declare(strict_types=1);

namespace PhelTest\Integration;

use Generator;
use Phel;
use Phel\Build\BuildFacade;
use Phel\Compiler\CompilerFacade;
use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironmentInterface;
use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;
use Phel\Lang\Registry;
use Phel\Lang\Symbol;
use Phel\Shared\CompileOptions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class IntegrationTest extends TestCase
{
    private static GlobalEnvironmentInterface $globalEnv;

    private CompilerFacade $compilerFacade;

    public static function setUpBeforeClass(): void
    {
        Phel::bootstrap(__DIR__);
        Symbol::resetGen();
        $globalEnv = GlobalEnvironmentSingleton::initializeNew();
        new BuildFacade()->compileFile(
            __DIR__ . '/../../../src/phel/core.phel',
            tempnam(sys_get_temp_dir(), 'phel-core'),
        );
        self::$globalEnv = $globalEnv;
    }

    protected function setUp(): void
    {
        $this->compilerFacade = new CompilerFacade();

        // Every fixture compiles into `user`, and a `def` from one is still
        // registered when the next runs, so the order decides the output.
        // SetVar/set-var.test defines `^:dynamic x`; after it, a read of `x` in
        // Def/nested-def-inside-fn.test sees a bindable var and compiles to
        // `\Phel::getDefinition` rather than `Registry::readRoot`, because
        // GlobalVarEmitter keeps the scope gate only for bindable vars.
        //
        // paratest hides this by running the fixtures in separate workers. The
        // coverage job runs unit and integration in one process with
        // executionOrder="random", which is where it surfaced.
        //
        // Only `user` is cleared: `phel.core` is compiled once in
        // setUpBeforeClass and every fixture needs it.
        Registry::getInstance()->removeNamespace('user');
    }

    #[DataProvider('providerIntegration')]
    public function test_integration(
        string $filename,
        string $phelCode,
        string $expectedGeneratedCode,
    ): void {
        $globalEnv = self::$globalEnv;
        $globalEnv->setNs('user');
        Symbol::resetGen();

        $options = new CompileOptions()
            ->setSource($filename);

        BuildFacade::enableBuildMode();
        // Phel compiles by evaluating top-level forms, so a fixture with
        // top-level side effects (e.g. `println`) runs during compilation.
        // Capture that output so it never leaks into the test runner.
        ob_start();
        try {
            $compiledCode = $this->compilerFacade->compile($phelCode, $options)->getPhpCode();
        } finally {
            ob_end_clean();
            BuildFacade::disableBuildMode();
        }

        self::assertSame(
            trim($expectedGeneratedCode),
            trim($compiledCode),
            'in ' . $filename,
        );
    }

    public static function providerIntegration(): Generator
    {
        $fixturesDir = realpath(__DIR__ . '/Fixtures');

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($fixturesDir),
            RecursiveIteratorIterator::LEAVES_ONLY,
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (!str_ends_with((string) $file->getRealPath(), '.test')) {
                continue;
            }

            $test = file_get_contents($file->getRealpath());

            if (preg_match('/--PHEL--\s*(?<phel>.*?)\s*--PHP--\s*(?<php>.*)/s', $test, $match)) {
                $filename = str_replace($fixturesDir . '/', '', $file->getRealPath());
                ['phel' => $phelCode, 'php' => $phpCode] = $match;

                yield $filename => [$filename, $phelCode, $phpCode];
            }
        }
    }
}
