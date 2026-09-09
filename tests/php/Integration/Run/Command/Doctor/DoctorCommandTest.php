<?php

declare(strict_types=1);

namespace PhelTest\Integration\Run\Command\Doctor;

use Gacela\Framework\Testing\GacelaTestCase;
use Phel\Config\PhelConfig;
use Phel\Run\Infrastructure\Command\DoctorCommand;
use Phel\Shared\Performance\OpcacheFileCache;
use PhelTest\Support\RemoveDirTrait;
use ReflectionClass;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;

use function bin2hex;
use function dirname;
use function file_put_contents;
use function mkdir;
use function random_bytes;
use function str_repeat;
use function sys_get_temp_dir;

final class DoctorCommandTest extends GacelaTestCase
{
    use RemoveDirTrait;

    private const string CURRENT_ID = 'a0d131c96acbcfdc5ebf8e9b6e5ff55a';

    private const string FOREIGN_ID = 'cf11b0a41c1f745a1bfd730ac5e2b089';

    protected function setUp(): void
    {
        $this->bootstrapGacelaWithConfig(__DIR__, [
            PhelConfig::TEMP_DIR => $this->containerTempDir(),
        ]);
    }

    protected function tearDown(): void
    {
        $this->cleanupContainerTempDirs();
        parent::tearDown();
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
        $this->bootstrapGacelaWithConfig(__DIR__, [
            PhelConfig::TEMP_DIR => $this->containerTempDir(),
            PhelConfig::SRC_DIRS => ['/absolute/src'],
        ]);

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

    public function test_doctor_reports_an_absent_opcache_file_cache(): void
    {
        $output = new BufferedOutput();

        new DoctorCommand()->run($this->createStub(InputInterface::class), $output);

        $rendered = $output->fetch();
        self::assertStringContainsString('Checking storage:', $rendered);
        self::assertStringContainsString('OPcache file cache: OK not created yet', $rendered);
    }

    public function test_doctor_reports_the_size_and_the_unreadable_subtrees_of_the_opcache_file_cache(): void
    {
        $projectRoot = sys_get_temp_dir() . '/phel-doctor-storage-' . bin2hex(random_bytes(6));
        $opcacheDir = $projectRoot . '/.phel/opcache';

        // The bin of the command's own file is what names the system id in
        // force, so planting one makes this process's id deterministic.
        $ownFile = (string) new ReflectionClass(DoctorCommand::class)->getFileName();
        $this->plantBin($opcacheDir, self::CURRENT_ID, $ownFile, str_repeat('x', 1024));
        $this->plantBin($opcacheDir, self::FOREIGN_ID, '/opt/app/boot.php', str_repeat('x', 512));

        $this->bootstrapGacelaWithConfig($projectRoot, [
            PhelConfig::TEMP_DIR => $this->containerTempDir(),
        ]);

        $output = new BufferedOutput();
        new DoctorCommand()->run($this->createStub(InputInterface::class), $output);
        $rendered = $output->fetch();

        $this->removeDir($projectRoot);

        self::assertStringContainsString('OPcache file cache: 1.50 KB in ' . $opcacheDir, $rendered);
        self::assertStringContainsString('Unreadable subtrees: 1 of 2', $rendered);
        self::assertStringContainsString('Reclaim it with phel cache:clear', $rendered);
    }

    public function test_doctor_succeeds_when_temp_dir_does_not_exist_yet(): void
    {
        $nonExistentTempDir = sys_get_temp_dir() . '/phel-doctor-fresh-' . uniqid('', true);

        $this->bootstrapGacelaWithConfig(__DIR__, [
            PhelConfig::TEMP_DIR => $nonExistentTempDir,
        ]);

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

    private function plantBin(string $opcacheDir, string $systemId, string $sourcePath, string $content): void
    {
        $path = $opcacheDir . '/' . $systemId . $sourcePath . OpcacheFileCache::BIN_SUFFIX;
        mkdir(dirname($path), 0o755, true);
        file_put_contents($path, $content);
    }
}
