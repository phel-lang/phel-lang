<?php

declare(strict_types=1);

namespace PhelTest\Integration\Interop\Command\Export;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use Phel\Interop\Infrastructure\Command\ExportCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class ExportCommandTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        Gacela::bootstrap(__DIR__, GacelaConfig::defaultPhpConfig());
    }

    public function test_export_command_multiple(): void
    {
        $command = new ExportCommand();

        $this->expectOutputRegex('~Exported namespaces:~');
        $this->expectOutputRegex('~TestCmdExportMultiple/Adder~');
        $this->expectOutputRegex('~TestCmdExportMultiple/Multiplier~');

        $command->run(
            $this->createStub(InputInterface::class),
            $this->stubOutput(),
        );

        $expectedCreatedFiles = [
            __DIR__ . '/PhelGenerated/TestCmdExportMultiple/Adder.php',
            __DIR__ . '/PhelGenerated/TestCmdExportMultiple/Multiplier.php',
        ];

        foreach ($expectedCreatedFiles as $file) {
            self::assertFileExists($file);
            unlink($file);
        }
    }

    private function stubOutput(): OutputInterface
    {
        $output = $this->createStub(OutputInterface::class);
        $output->method('writeln')
            ->willReturnCallback(static fn (string $str) => print $str . PHP_EOL);

        return $output;
    }
}
