<?php

declare(strict_types=1);

namespace PhelTest\Integration\Run\Command\Repl;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use Generator;
use Override;
use Phel\Config\PhelConfig;
use Phel\Run\Infrastructure\Command\ReplCommand;
use PhelTest\Integration\Run\Command\AbstractTestCommand;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class ReplCommandTest extends AbstractTestCommand
{
    use ReplCommandTestTrait;

    private string $previousCwd = '';

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->previousCwd = getcwd() ?: '';

        // Namespace resolution keys off Gacela's app root, so it has to equal
        // the working directory: `require-current-dir.test` requires
        // `util.phel` from where the REPL runs, while the core-lib fixtures
        // need `src/phel` on the source path. Configured inline rather than via
        // a `phel-config.php` here, because AbstractTestCommand bootstraps every
        // REPL test from this directory and a config file would silently
        // reconfigure all of them.
        chdir(__DIR__);
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->addAppConfigKeyValues([
                PhelConfig::SRC_DIRS => ['../../../../../../src/phel', '.'],
                PhelConfig::VENDOR_DIR => '',
            ]);
        });
    }

    #[Override]
    protected function tearDown(): void
    {
        chdir($this->previousCwd);
    }

    #[DataProvider('providerIntegration')]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_integration(string $expectedOutput, InputLine ...$inputs): void
    {
        $io = $this->createReplTestIo();
        $io->setInputs(...$inputs);
        $this->prepareRunFactory($io);

        $repl = $this->createReplCommand();
        $repl->run(
            $this->createStub(InputInterface::class),
            $this->createStub(OutputInterface::class),
        );

        $replOutput = $io->getOutputString();

        self::assertSame(trim($expectedOutput), trim($replOutput));
    }

    /**
     * This is doing the same as the test above except that it will load the core lib before.
     * We split it because it takes some time to load the core lib before every test.
     */
    #[DataProvider('providerIntegrationWithCoreLib')]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_integration_with_core_lib(string $expectedOutput, InputLine ...$inputs): void
    {
        $io = $this->createReplTestIo();
        $io->setInputs(...$inputs);
        $this->prepareRunFactory($io);

        $repl = $this->createReplCommandWithCoreLib();
        $repl->run(
            $this->createStub(InputInterface::class),
            $this->createStub(OutputInterface::class),
        );

        $replOutput = $io->getOutputString();

        self::assertSame(trim($expectedOutput), trim($replOutput));
    }

    public static function providerIntegration(): Generator
    {
        return self::buildDataProviderFromDirectory(realpath(__DIR__ . '/Fixtures'));
    }

    public static function providerIntegrationWithCoreLib(): Generator
    {
        return self::buildDataProviderFromDirectory(realpath(__DIR__ . '/FixturesWithCoreLib'));
    }

    private static function buildDataProviderFromDirectory(string $fixturesDir): Generator
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($fixturesDir),
            RecursiveIteratorIterator::LEAVES_ONLY,
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (preg_match('/\.test$/', $file->getRealPath()) === 0) {
                continue;
            }

            if (preg_match('/\.test$/', $file->getRealPath()) === false) {
                continue;
            }

            $fileContent = file_get_contents($file->getRealpath());
            $filename = str_replace($fixturesDir . '/', '', $file->getRealPath());

            yield $filename => [
                $fileContent,
                ...self::getInputs($fileContent),
            ];
        }
    }

    private function createReplCommand(): ReplCommand
    {
        return new ReplCommand();
    }

    private function createReplCommandWithCoreLib(): ReplCommand
    {
        $replStartupFile = __DIR__ . '/../../../../../../resources/repl/startup.phel';

        return new ReplCommand()->setReplStartupFile($replStartupFile);
    }

    /**
     * @return InputLine[]
     */
    private static function getInputs(string $fileContent): array
    {
        $inputs = [];

        foreach (explode(PHP_EOL, $fileContent) as $line) {
            preg_match('/(?<prompt>(?:[\w\\\\.-]+|\.{4}):\d+> ?)(?<phel_code>.+)?/', $line, $out);
            if ($out !== []) {
                $prompt = $out['prompt'];
                $code = $out['phel_code'] ?? '';
                $inputs[] = new InputLine($prompt, $code);
            }
        }

        return $inputs;
    }

}
