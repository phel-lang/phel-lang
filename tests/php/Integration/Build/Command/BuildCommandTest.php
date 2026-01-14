<?php

declare(strict_types=1);

namespace PhelTest\Integration\Build\Command;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use Phel\Build\Infrastructure\Command\BuildCommand;
use Phel\Phel;
use PhelTest\Integration\Util\DirectoryUtil;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\OutputInterface;

use function ob_get_clean;
use function ob_start;

final class BuildCommandTest extends TestCase
{
    private BuildCommand $command;

    public static function tearDownAfterClass(): void
    {
        DirectoryUtil::removeDir(__DIR__ . '/out');
    }

    protected function setUp(): void
    {
        $this->command = new BuildCommand();
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_build_project(): void
    {
        DirectoryUtil::removeDir(__DIR__ . '/out');
        $this->bootstrapGacela();

        ob_start();
        $this->command->run(
            new ArrayInput([
                '--no-source-map' => true,
                '--no-cache' => true,
            ]),
            $this->createStub(OutputInterface::class),
        );
        $string = ob_get_clean();

        self::assertMatchesRegularExpression('/This is printed from no-cache.phel/', $string);
        self::assertMatchesRegularExpression('/This is printed from hello.phel/', $string);

        self::assertFileExists(__DIR__ . '/out/phel/core.phel');
        self::assertFileExists(__DIR__ . '/out/phel/core.php');
        self::assertFileExists(__DIR__ . '/out/test_ns/hello.phel');
        self::assertFileExists(__DIR__ . '/out/test_ns/hello.php');
    }

    #[Depends('test_build_project')]
    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_build_project_cached(): void
    {
        $targetFile = __DIR__ . '/out/test_ns/hello.php';

        // Skip if dependency didn't set up the expected state (can happen with process isolation)
        if (!file_exists($targetFile)) {
            self::markTestSkipped('Required file from test_build_project not found');
        }

        $this->bootstrapGacela();

        // Mark file cache invalid by setting the modification time to 0
        touch($targetFile, 1);

        ob_start();
        $this->command->run(
            new ArrayInput([
                '--no-source-map' => true,
            ]),
            $this->createStub(OutputInterface::class),
        );
        $string = ob_get_clean();

        // Both files should print during build:
        // - no-cache.phel: always recompiled (in no-cache list), executes during compilation
        // - hello.phel: cache invalid (touched), gets recompiled, executes during compilation
        self::assertMatchesRegularExpression('/This is printed from no-cache.phel/', $string);
        self::assertMatchesRegularExpression('/This is printed from hello.phel/', $string);

        self::assertFileExists(__DIR__ . '/out/phel/core.phel');
        self::assertFileExists(__DIR__ . '/out/phel/core.php');
        self::assertFileExists(__DIR__ . '/out/test_ns/hello.phel');
        self::assertFileExists(__DIR__ . '/out/test_ns/hello.php');
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_out_main_file(): void
    {
        $this->bootstrapGacela();

        ob_start();
        $this->command->run(
            new ArrayInput([
                '--no-source-map' => true,
                '--no-cache' => true,
            ]),
            $this->createStub(OutputInterface::class),
        );
        ob_end_clean();

        $actual = file_get_contents(__DIR__ . '/out/main.php');
        $expected = <<<'TXT'
<?php declare(strict_types=1);

require_once dirname(__DIR__) . "/vendor/autoload.php";

// Normalize argv: program is $argv[0], user args are the rest
\Phel\Phel::setupRuntimeArgs($argv[0] ?? __FILE__, array_slice($argv ?? [], 1));

$compiledFile = __DIR__ . "/test_ns/hello.php";

require_once $compiledFile;
TXT;
        self::assertSame($expected, $actual);
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_no_entrypoint_when_namespace_is_not_set(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->addAppConfig('config/phel-config-no-namespace.php');
        });

        if (file_exists(__DIR__ . '/out/main.php')) {
            unlink(__DIR__ . '/out/main.php');
        }

        ob_start();
        $this->command->run(
            new ArrayInput([
                '--no-source-map' => true,
                '--no-cache' => true,
            ]),
            $this->createStub(OutputInterface::class),
        );
        ob_end_clean();

        $this->assertFileDoesNotExist(__DIR__ . '/out/main.php');
    }

    private function bootstrapGacela(): void
    {
        Phel::bootstrap(__DIR__);
    }
}
