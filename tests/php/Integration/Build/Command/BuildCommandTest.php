<?php

declare(strict_types=1);

namespace PhelTest\Integration\Build\Command;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use Phel\Build\Infrastructure\Command\BuildCommand;
use PhelTest\Integration\Util\DirectoryUtil;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\OutputInterface;

use function ob_get_clean;
use function ob_start;

final class BuildCommandTest extends TestCase
{
    private BuildCommand $command;

    protected function setUp(): void
    {
        $this->command = new BuildCommand();
    }

    /**
     * @runInSeparateProcess
     *
     * @preserveGlobalState disabled
     */
    public function test_build_project(): void
    {
        DirectoryUtil::removeDir(__DIR__ . '/out');

        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->addAppConfig('config/phel-config.php');
        });

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

    /**
     * @runInSeparateProcess
     *
     * @preserveGlobalState disabled
     *
     * @depends test_build_project
     */
    public function test_build_project_cached(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->addAppConfig('config/phel-config.php');
        });

        // Mark file cache invalid by setting the modification time to 0
        touch(__DIR__ . '/out/test_ns/hello.php', 1);

        ob_start();
        $this->command->run(
            new ArrayInput([
                '--no-source-map' => true,
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

    /**
     * @runInSeparateProcess
     *
     * @preserveGlobalState disabled
     */
    public function test_out_main_file(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->addAppConfig('config/phel-config.php');
        });

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

$compiledFile = __DIR__ . "/test_ns/hello.php";

require_once $compiledFile;
TXT;
        self::assertSame($expected, $actual);
    }

    /**
     * @runInSeparateProcess
     *
     * @preserveGlobalState disabled
     */
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
}
