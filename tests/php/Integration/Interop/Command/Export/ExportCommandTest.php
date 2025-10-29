<?php

declare(strict_types=1);

namespace PhelTest\Integration\Interop\Command\Export;

use Phel\Interop\Infrastructure\Command\ExportCommand;
use Phel\Phel;
use PhelTest\Integration\Interop\Command\Export\PhelGenerated\TestCmdExportMultiple\Adder;
use PhelTest\Integration\Interop\Command\Export\PhelGenerated\TestCmdExportMultiple\Multiplier;
use PhelTest\Integration\Util\DirectoryUtil;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class ExportCommandTest extends TestCase
{
    protected function setUp(): void
    {
        DirectoryUtil::removeDir(__DIR__ . '/PhelGenerated/');
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_export_command_multiple(): void
    {
        Phel::bootstrap(__DIR__);
        $command = new ExportCommand();

        $this->expectOutputRegex('~Exported namespaces:~');
        $this->expectOutputRegex('~TestCmdExportMultiple/Adder~');
        $this->expectOutputRegex('~TestCmdExportMultiple/Multiplier~');

        $command->run(
            $this->createStub(InputInterface::class),
            $this->stubOutput(),
        );

        self::assertFileExists(__DIR__ . '/PhelGenerated/TestCmdExportMultiple/Adder.php');
        self::assertFileExists(__DIR__ . '/PhelGenerated/TestCmdExportMultiple/Multiplier.php');

        self::assertSame(3, (new Adder())->adder1(1, 2));
        self::assertSame(9, (new Multiplier())->multiplier2(3, 3));
    }

    private function stubOutput(): OutputInterface
    {
        $output = $this->createStub(OutputInterface::class);
        $output->method('writeln')
            ->willReturnCallback(static fn (string $str): int => print $str . PHP_EOL);

        return $output;
    }
}
