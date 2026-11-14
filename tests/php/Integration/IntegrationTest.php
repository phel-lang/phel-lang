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

    /** @var array<string, mixed> */
    private static array $globalEnvSnapshot;

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
        self::$globalEnvSnapshot = $globalEnv->snapshot();
    }

    protected function setUp(): void
    {
        self::$globalEnv->restore(self::$globalEnvSnapshot);
        GlobalEnvironmentSingleton::setInstance(self::$globalEnv);
        $this->compilerFacade = new CompilerFacade();

        Registry::getInstance()->removeNamespace('user');
    }

    /**
     * @param list<string> $expectedDeprecations
     */
    #[DataProvider('providerIntegration')]
    public function test_integration(
        string $filename,
        string $phelCode,
        string $expectedGeneratedCode,
        array $expectedDeprecations,
    ): void {
        $globalEnv = GlobalEnvironmentSingleton::getInstance();
        $globalEnv->setNs('user');
        Symbol::resetGen();

        $options = new CompileOptions()
            ->setSource($filename);

        $deprecations = [];
        $previousHandler = null;
        $previousHandler = set_error_handler(
            static function (int $level, string $message, string $file, int $line) use (&$deprecations, &$previousHandler): bool {
                if ($level !== E_USER_DEPRECATED) {
                    return $previousHandler !== null && $previousHandler($level, $message, $file, $line) !== false;
                }

                $deprecations[] = $message;

                return true;
            },
        );

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
            restore_error_handler();
        }

        self::assertSame(
            trim($expectedGeneratedCode),
            trim($compiledCode),
            'in ' . $filename,
        );
        self::assertSame($expectedDeprecations, $deprecations, 'deprecations in ' . $filename);
    }

    public function test_fixture_warnings_reach_the_previous_error_handler(): void
    {
        $warnings = [];
        set_error_handler(static function (int $level, string $message, string $file, int $line) use (&$warnings): bool {
            $warnings[] = [$level, $message, $file, $line];

            return true;
        });

        try {
            $this->test_integration(
                'unexpected-warning.phel',
                '(php/trigger_error "unexpected fixture warning" php/E_USER_WARNING)',
                'trigger_error("unexpected fixture warning", E_USER_WARNING);',
                [],
            );
        } finally {
            restore_error_handler();
        }

        self::assertCount(1, $warnings);
        self::assertSame(E_USER_WARNING, $warnings[0][0]);
        self::assertSame('unexpected fixture warning', $warnings[0][1]);
        self::assertNotEmpty($warnings[0][2]);
        self::assertGreaterThan(0, $warnings[0][3]);
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

            // An optional --DEPRECATIONS-- section lists, one per line, the
            // deprecation notices a fixture raises on purpose. Any other
            // notice fails the fixture.
            if (preg_match('/--PHEL--\s*(?<phel>.*?)\s*(?:--DEPRECATIONS--\s*(?<deprecations>.*?)\s*)?--PHP--\s*(?<php>.*)/s', $test, $match)) {
                $filename = str_replace($fixturesDir . '/', '', $file->getRealPath());
                ['phel' => $phelCode, 'php' => $phpCode] = $match;
                $deprecations = $match['deprecations'] === '' ? [] : explode("\n", $match['deprecations']);

                yield $filename => [$filename, $phelCode, $phpCode, $deprecations];
            }
        }
    }
}
