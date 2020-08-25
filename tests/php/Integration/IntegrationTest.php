<?php

declare(strict_types=1);

namespace PhelTest\Integration;

use Generator;
use Phel\Analyzer;
use Phel\Emitter;
use Phel\GlobalEnvironment;
use Phel\Lang\Symbol;
use Phel\Lexer;
use Phel\Reader;
use Phel\Runtime;
use PHPUnit\Framework\TestCase;

final class IntegrationTest extends TestCase
{
    private static $globalEnv;

    public static function setUpBeforeClass(): void
    {
        Symbol::resetGen();
        $globalEnv = new GlobalEnvironment();
        $rt = Runtime::initializeNew($globalEnv);
        $rt->addPath('phel\\', [__DIR__ . '/../../src/phel/']);
        $rt->loadNs('phel\core');
        self::$globalEnv = $globalEnv;
    }

    /**
     * @dataProvider integrationDataProvider
     */
    public function testIntegration(string $filename, string $phelCode, string $generatedCode): void
    {
        $this->doIntegrationTest($filename, $phelCode, $generatedCode);
    }

    protected function doIntegrationTest(string $filename, string $phelCode, string $generatedCode): void
    {
        $globalEnv = self::$globalEnv;
        $globalEnv->setNs('user');
        Symbol::resetGen();
        $reader = new Reader($globalEnv);
        $analyzer = new Analyzer($globalEnv);
        $emitter = Emitter::createWithoutSourceMap();
        $lexer = new Lexer();
        $tokenStream = $lexer->lexString($phelCode);

        $compiledCode = [];
        while (true) {
            $readAst = $reader->readNext($tokenStream);

            if (!$readAst) {
                break;
            }

            $compiledCode[] = $emitter->emitNodeAndEval(
                $analyzer->analyzeInEmptyEnv($readAst->getAst())
            );
        }
        $compiledCode = trim(implode('', $compiledCode));
        self::assertEquals($generatedCode, $compiledCode, 'in ' . $filename);
    }

    public function integrationDataProvider(): Generator
    {
        $fixturesDir = realpath(__DIR__ . '/Fixtures');

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($fixturesDir),
            \RecursiveIteratorIterator::LEAVES_ONLY
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

                yield [$filename, $phelCode, $phpCode];
            }
        }
    }
}
