<?php

declare(strict_types=1);

namespace PhelTest\Integration\Command\Export;

use Gacela\Framework\Config;
use PhelTest\Integration\Command\AbstractCommandTest;
use PhelTest\Integration\Util\DirectoryUtil;
use Symfony\Component\Console\Input\InputInterface;

final class ExportCommandTest extends AbstractCommandTest
{
    public static function setUpBeforeClass(): void
    {
        Config::setApplicationRootDir(__DIR__);
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

        $command->run(
            $this->createStub(InputInterface::class),
            $this->stubOutput()
        );

        self::assertFileExists(__DIR__ . '/PhelGenerated/TestCmdExportMultiple/Adder.php');
        self::assertFileExists(__DIR__ . '/PhelGenerated/TestCmdExportMultiple/Multiplier.php');

        DirectoryUtil::removeDir(__DIR__ . '/PhelGenerated/');
    }
}
