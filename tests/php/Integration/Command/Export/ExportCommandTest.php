<?php

declare(strict_types=1);

namespace PhelTest\Integration\Command\Export;

use Gacela\Framework\Config;
use Phel\Command\CommandFactory;
use Phel\Runtime\RuntimeSingleton;
use PhelTest\Integration\Util\DirectoryUtil;
use PHPUnit\Framework\TestCase;

final class ExportCommandTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        Config::setApplicationRootDir(__DIR__);
    }

    public function setUp(): void
    {
        RuntimeSingleton::reset();
    }

    public function testExportCommandMultiple(): void
    {
        $command = $this
            ->createCommandFactory()
            ->createExportCommand()
            ->addRuntimePath('test-cmd-export-multiple\\', [__DIR__ . '/src/test-cmd-export-multiple/']);

        $this->expectOutputRegex('~Exported namespaces:~');
        $this->expectOutputRegex('~TestCmdExportMultiple/Adder~');
        $this->expectOutputRegex('~TestCmdExportMultiple/Multiplier~');

        $command->run();

        self::assertFileExists(__DIR__ . '/PhelGenerated/TestCmdExportMultiple/Adder.php');
        self::assertFileExists(__DIR__ . '/PhelGenerated/TestCmdExportMultiple/Multiplier.php');

        DirectoryUtil::removeDir(__DIR__ . '/PhelGenerated/');
    }

    private function createCommandFactory(): CommandFactory
    {
        return new CommandFactory();
    }
}
