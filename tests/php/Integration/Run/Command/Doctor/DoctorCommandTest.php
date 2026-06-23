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

    public function test_doctor_command_reports_opcache_performance(): void
    {
        $output = new BufferedOutput();

        new DoctorCommand()->run(
            $this->createStub(InputInterface::class),
            $output,
        );

        $rendered = $output->fetch();
        self::assertStringContainsString('Checking performance:', $rendered);
        self::assertStringContainsString('OPcache CLI caching:', $rendered);
    }

    public function test_doctor_reports_a_configuration_section(): void
    {
        $output = new BufferedOutput();

        new DoctorCommand()->run(
            $this->createStub(InputInterface::class),
            $output,
        );

        self::assertStringContainsString('Checking configuration:', $output->fetch());
    }

    public function test_doctor_fails_when_configuration_has_an_error(): void
    {
        $this->resetContainer();
        $tempDir = $this->containerTempDir();
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config) use ($tempDir): void {
            $config->addAppConfigKeyValue(PhelConfig::TEMP_DIR, $tempDir);
            $config->addAppConfigKeyValue(PhelConfig::SRC_DIRS, ['/absolute/src']);
        });

        $output = new BufferedOutput();
        $exitCode = new DoctorCommand()->run(
            $this->createStub(InputInterface::class),
            $output,
        );

        $rendered = $output->fetch();
        self::assertSame(1, $exitCode, 'an absolute src dir is a config error and must fail doctor');
        self::assertStringContainsString('Checking configuration:', $rendered);
        self::assertStringContainsString('/absolute/src', $rendered);
        self::assertStringContainsString('does not meet all requirements', $rendered);
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
