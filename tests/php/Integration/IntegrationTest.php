<?php

declare(strict_types=1);

namespace PhelTest\Integration;

use Generator;
use Phel\Compiler\AnalyzerInterface;
use Phel\Compiler\CompilerFactory;
use Phel\Compiler\EmitterInterface;
use Phel\Compiler\GlobalEnvironment;
use Phel\Compiler\NodeEnvironment;
use Phel\Compiler\ReaderInterface;
use Phel\Lang\Symbol;
use Phel\Runtime;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class IntegrationTest extends TestCase
{
    private static GlobalEnvironment $globalEnv;

    public static function setUpBeforeClass(): void
    {
        Symbol::resetGen();
        $globalEnv = new GlobalEnvironment();
        $rt = Runtime::initializeNew($globalEnv);
        $rt->addPath('phel\\', [__DIR__ . '/../../src/phel/']);
        $rt->loadNs('phel\core');
        static::$globalEnv = $globalEnv;
    }

    /**
     * @dataProvider providerIntegration
     */
    public function testIntegration(string $filename, string $phelCode, string $expectedGeneratedCode): void
    {
        $globalEnv = static::$globalEnv;
        $globalEnv->setNs('user');
        Symbol::resetGen();

        $compilerFactory = new CompilerFactory();

        $compiledCode = $this->compilePhelCode(
            $compilerFactory->createReader($globalEnv),
            $compilerFactory->createAnalyzer($globalEnv),
            $compilerFactory->createEmitter($enableSourceMaps = false),
            $compilerFactory->createLexer()->lexString($phelCode)
        );

        self::assertEquals($expectedGeneratedCode, $compiledCode, 'in ' . $filename);
    }

    public function providerIntegration(): Generator
    {
        $fixturesDir = realpath(__DIR__ . '/Fixtures');

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($fixturesDir),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $file) {
            if (!preg_match('/\.test$/', (string)$file)) {
                continue;
            }

            $test = file_get_contents($file->getRealpath());

            if (preg_match('/--PHEL--\s*(.*?)\s*--PHP--\s*(.*)/s', $test, $match)) {
                $filename = str_replace($fixturesDir . '/', '', $file);
                $phelCode = $match[1];
                $phpCode = trim($match[2]);

                yield $filename => [$filename, $phelCode, $phpCode];
            }
        }
    }

    private function compilePhelCode(
        ReaderInterface $reader,
        AnalyzerInterface $analyzer,
        EmitterInterface $emitter,
        Generator $tokenStream
    ): string {
        $compiledCode = [];

        while (true) {
            $readAst = $reader->readNext($tokenStream);
            if (!$readAst) {
                break;
            }

            $node = $analyzer->analyze($readAst->getAst(), NodeEnvironment::empty());
            $compiledCode[] = $emitter->emitNodeAndEval($node);
        }

        return trim(implode('', $compiledCode));
    }
}
