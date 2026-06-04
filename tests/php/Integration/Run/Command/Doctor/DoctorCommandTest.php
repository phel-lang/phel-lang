<?php

declare(strict_types=1);

namespace PhelTest\Integration\Run\Command\Doctor;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use Gacela\Framework\Testing\ContainerFixture;
use Phel\Config\PhelConfig;
use Phel\Run\Infrastructure\Command\DoctorCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;

final class DoctorCommandTest extends TestCase
{
    use ContainerFixture;

    protected function setUp(): void
    {
        $this->resetContainer();
        $tempDir = $this->containerTempDir();
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config) use ($tempDir): void {
            $config->addAppConfigKeyValue(PhelConfig::TEMP_DIR, $tempDir);
        });
    }

    protected function tearDown(): void
    {
        $this->cleanupContainerTempDirs();
    }

    public function test_doctor_command_outputs_success(): void
    {
        $output = new BufferedOutput();

        new DoctorCommand()->run(
            $this->createStub(InputInterface::class),
            $output,
        );

        self::assertMatchesRegularExpression('/Your system meets all requirements/', $output->fetch());
    }

    public function test_doctor_succeeds_when_temp_dir_does_not_exist_yet(): void
    {
        $this->resetContainer();
        $nonExistentTempDir = sys_get_temp_dir() . '/phel-doctor-fresh-' . uniqid('', true);

        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config) use ($nonExistentTempDir): void {
            $config->addAppConfigKeyValue(PhelConfig::TEMP_DIR, $nonExistentTempDir);
        });

        self::assertDirectoryDoesNotExist($nonExistentTempDir);

        $exitCode = new DoctorCommand()->run(
            $this->createStub(InputInterface::class),
            new BufferedOutput(),
        );

        if (is_dir($nonExistentTempDir)) {
            rmdir($nonExistentTempDir);
        }

        self::assertSame(0, $exitCode, 'doctor should exit 0 even when temp dir did not exist beforehand');
    }
}
