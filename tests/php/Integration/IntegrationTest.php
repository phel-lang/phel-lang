<?php

declare(strict_types=1);

namespace PhelTest\Integration;

use Gacela\Framework\Gacela;
use Generator;
use Phel\Build\BuildFacade;
use Phel\Compiler\CompilerFacade;
use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironment;
use Phel\Compiler\Infrastructure\CompileOptions;
use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;
use Phel\Lang\Symbol;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class IntegrationTest extends TestCase
{
    private static GlobalEnvironment $globalEnv;

    private CompilerFacade $compilerFacade;

    public static function setUpBeforeClass(): void
    {
        Gacela::bootstrap(__DIR__);
        Symbol::resetGen();
        $globalEnv = GlobalEnvironmentSingleton::initializeNew();
        (new BuildFacade())->compileFile(
            __DIR__ . '/../../../src/phel/core.phel',
            tempnam(sys_get_temp_dir(), 'phel-core'),
        );
        self::$globalEnv = $globalEnv;
    }

    protected function setUp(): void
    {
        $this->compilerFacade = new CompilerFacade();
    }

    /**
     * @dataProvider providerIntegration
     */
    public function test_integration(
        string $filename,
        string $phelCode,
        string $expectedGeneratedCode,
    ): void {
        $globalEnv = self::$globalEnv;
        $globalEnv->setNs('user');
        Symbol::resetGen();

        $options = (new CompileOptions())
            ->setSource($filename);

        BuildFacade::enableBuildMode();
        $compiledCode = $this->compilerFacade->compile($phelCode, $options)->getPhpCode();
        BuildFacade::disableBuildMode();

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
            if (preg_match('/\.test$/', $file->getRealPath()) === 0) {
                continue;
            }

            if (preg_match('/\.test$/', $file->getRealPath()) === 0) {
                continue;
            }

            if (preg_match('/\.test$/', $file->getRealPath()) === false) {
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
