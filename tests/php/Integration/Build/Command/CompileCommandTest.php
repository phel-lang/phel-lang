<?php

declare(strict_types=1);

namespace PhelTest\Integration\Build\Command;

use Gacela\Framework\Gacela;
use Phel\Build\BuildFacade;
use Phel\Build\BuildFacadeInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\OutputInterface;

final class CompileCommandTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        Gacela::bootstrap(__DIR__);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_compile_project(): void
    {
        $command = $this->createBuildFacade()->getCompileCommand();

        $this->expectOutputString("This is printed\n");

        $command->run(
            new ArrayInput([
                '--no-source-map' => true,
                '--no-cache' => true,
            ]),
            $this->stubOutput()
        );

        self::assertFileExists(__DIR__ . '/out/phel/core.phel');
        self::assertFileExists(__DIR__ . '/out/phel/core.php');
        self::assertFileExists(__DIR__ . '/out/hello.phel');
        self::assertFileExists(__DIR__ . '/out/hello.php');
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * @depends test_compile_project
     */
    public function test_compile_project_cached(): void
    {
        // Mark file cache invalid by setting the modification time to 0
        touch(__DIR__ . '/out/hello.php', 1);

        $command = $this->createBuildFacade()->getCompileCommand();

        $this->expectOutputString("This is printed\n");

        $command->run(
            new ArrayInput([
                '--no-source-map' => true,
            ]),
            $this->stubOutput()
        );

        self::assertFileExists(__DIR__ . '/out/phel/core.phel');
        self::assertFileExists(__DIR__ . '/out/phel/core.php');
        self::assertFileExists(__DIR__ . '/out/hello.phel');
        self::assertFileExists(__DIR__ . '/out/hello.php');
    }

    private function createBuildFacade(): BuildFacadeInterface
    {
        return new BuildFacade();
    }

    private function stubOutput(): OutputInterface
    {
        $output = $this->createStub(OutputInterface::class);
        $output->method('writeln')
            ->willReturnCallback(fn (string $str) => print $str . PHP_EOL);

        return $output;
    }
}
