<?php

declare(strict_types=1);

namespace PhelTest\Integration;

use Generator;
use Phel\Compiler\Analyzer;
use Phel\Compiler\CompilerFactory;
use Phel\Compiler\GlobalEnvironment;
use Phel\Compiler\Lexer;
use Phel\Compiler\NodeEnvironment;
use Phel\Lang\Symbol;
use Phel\Runtime;
use PHPUnit\Framework\TestCase;

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
    public function testIntegration(string $filename, string $phelCode, string $generatedCode): void
    {
        $globalEnv = static::$globalEnv;
        $globalEnv->setNs('user');
        Symbol::resetGen();
        $reader = (new CompilerFactory())->createReader($globalEnv);
        $analyzer = new Analyzer($globalEnv);
        $emitter = (new CompilerFactory())->createEmitter($enableSourceMaps = false);
        $lexer = new Lexer();
        $tokenStream = $lexer->lexString($phelCode);

        $compiledCode = [];
        while (true) {
            $readAst = $reader->readNext($tokenStream);

            if (!$readAst) {
                break;
            }

            $compiledCode[] = $emitter->emitNodeAndEval(
                $analyzer->analyze($readAst->getAst(), NodeEnvironment::empty())
            );
        }
        $compiledCode = trim(implode('', $compiledCode));
        self::assertEquals($generatedCode, $compiledCode, 'in ' . $filename);
    }

    public function providerIntegration(): Generator
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
