<?php

declare(strict_types=1);

namespace PhelTest\Integration;

use Generator;
use Phel\Build\BuildFacade;
use Phel\Compiler\Analyzer\Environment\GlobalEnvironment;
use Phel\Compiler\Analyzer\Environment\GlobalEnvironmentSingleton;
use Phel\Compiler\Compiler\CompileOptions;
use Phel\Compiler\CompilerFacade;
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
        Symbol::resetGen();
        $globalEnv = GlobalEnvironmentSingleton::initializeNew();
        (new BuildFacade())->compileFile(
            __DIR__ . '/../../../src/phel/core.phel',
            tempnam(sys_get_temp_dir(), 'phel-core')
        );
        self::$globalEnv = $globalEnv;
    }

    public function setUp(): void
    {
        $this->compilerFacade = new CompilerFacade();
    }

    /**
     * @dataProvider providerIntegration
     */
    public function test_integration(
        string $filename,
        string $phelCode,
        string $expectedGeneratedCode
    ): void {
        $globalEnv = self::$globalEnv;
        $globalEnv->setNs('user');
        Symbol::resetGen();

        $options = (new CompileOptions())
            ->setSource($filename);

        $compiledCode = $this->compilerFacade->compile($phelCode, $options)->getCode();

        self::assertSame(
            trim($expectedGeneratedCode),
            trim($compiledCode),
            'in ' . $filename
        );
    }

    public function providerIntegration(): Generator
    {
        $fixturesDir = realpath(__DIR__ . '/Fixtures');

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($fixturesDir),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (!preg_match('/\.test$/', $file->getRealPath())) {
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
