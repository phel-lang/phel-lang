<?php

declare(strict_types=1);

namespace PhelTest\Integration;

use Generator;
use Phel\Compiler\Analyzer\Environment\GlobalEnvironment;
use Phel\Compiler\CompilerFacade;
use Phel\Lang\Symbol;
use Phel\Runtime\RuntimeSingleton;
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
        $globalEnv = new GlobalEnvironment();
        $rt = RuntimeSingleton::initializeNew($globalEnv);
        $rt->addPath('phel\\', [__DIR__ . '/../../src/phel/']);
        $rt->loadNs('phel\core');
        self::$globalEnv = $globalEnv;
    }

    public function setUp(): void
    {
        $this->compilerFacade = new CompilerFacade();
    }

    /**
     * @dataProvider providerIntegration
     */
    public function testIntegration(
        string $filename,
        string $phelCode,
        string $expectedGeneratedCode
    ): void {
        $globalEnv = self::$globalEnv;
        $globalEnv->setNs('user');
        Symbol::resetGen();

        $compiledCode = $this->compilerFacade->compile($phelCode);

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

            if (preg_match('/--PHEL--\s*(.*?)\s*--PHP--\s*(.*)/s', $test, $match)) {
                $filename = str_replace($fixturesDir . '/', '', $file->getRealPath());
                $phelCode = $match[1];
                $phpCode = $match[2];

                yield $filename => [$filename, $phelCode, $phpCode];
            }
        }
    }
}
