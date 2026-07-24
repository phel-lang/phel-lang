<?php

declare(strict_types=1);

namespace PhelTest\Unit\Run\Application;

use Phel\Run\Application\DataReadersLoader;
use Phel\Run\Domain\Runner\DataReadersLoadException;
use Phel\Shared\CompiledFile;
use Phel\Shared\Facade\BuildFacadeInterface;
use Phel\Shared\NamespaceInformation;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function array_merge;
use function file_put_contents;
use function mkdir;
use function rmdir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

final class DataReadersLoaderTest extends TestCase
{
    /** @var list<string> */
    private array $tempDirs = [];

    /** @var list<string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }

        foreach ($this->tempDirs as $dir) {
            @rmdir($dir);
        }

        $this->tempFiles = [];
        $this->tempDirs = [];
    }

    public function test_it_does_nothing_when_no_data_readers_file_exists(): void
    {
        $dir = $this->makeTempDir();
        $buildFacade = $this->createMock(BuildFacadeInterface::class);

        $buildFacade->expects(self::never())->method('evalFile');
        $buildFacade->expects(self::never())->method('getDependenciesForNamespace');

        new DataReadersLoader($buildFacade)->load([$dir]);
    }

    public function test_it_loads_reader_dependencies_then_data_readers_file(): void
    {
        $dir = $this->makeTempDir();
        $file = $dir . '/data-readers.phel';
        $this->writeFile($file, ";; placeholder\n");

        $readerFile = $dir . '/phel-reader.phel';
        $this->writeFile($readerFile, ";; placeholder\n");

        $readerInfo = new NamespaceInformation(
            $readerFile,
            'phel.reader',
            [],
        );

        $calls = [];

        $buildFacade = $this->createStub(BuildFacadeInterface::class);
        $buildFacade->method('getDependenciesForNamespace')
            ->willReturnCallback(static function (array $dirs, array $ns) use (&$calls, $readerInfo): array {
                $calls[] = ['deps', $ns];
                return [$readerInfo];
            });

        $buildFacade->method('evalFile')
            ->willReturnCallback(static function (string $path) use (&$calls): CompiledFile {
                $calls[] = ['eval', $path];
                return new CompiledFile($path, $path, 'phel.reader');
            });

        new DataReadersLoader($buildFacade)->load([$dir]);

        self::assertSame('deps', $calls[0][0]);
        self::assertSame(['phel.reader', 'phel.core'], $calls[0][1]);
        self::assertSame('eval', $calls[1][0]);
        self::assertSame($readerFile, $calls[1][1]);
        self::assertSame('eval', $calls[2][0]);
        self::assertSame(realpath($file), realpath((string) $calls[2][1]));
    }

    public function test_it_reports_the_data_reader_files_when_reader_bootstrap_fails(): void
    {
        // The file exists, so the user opted into these readers. A failure to
        // bootstrap `phel.reader` must name the file instead of leaving the
        // tags unregistered and failing later as an unreadable literal.
        $dir = $this->makeTempDir();
        $file = $dir . '/data-readers.phel';
        $this->writeFile($file, ";; placeholder\n");

        $buildFacade = $this->createMock(BuildFacadeInterface::class);
        $buildFacade->method('getDependenciesForNamespace')
            ->willThrowException(new RuntimeException('cannot resolve'));

        $buildFacade->expects(self::never())->method('evalFile');

        $this->expectException(DataReadersLoadException::class);
        $this->expectExceptionMessageMatches('/data-readers\.phel.*cannot resolve/');

        new DataReadersLoader($buildFacade)->load([$dir]);
    }

    private function makeTempDir(): string
    {
        $dir = sys_get_temp_dir() . '/phel-data-readers-' . uniqid();
        mkdir($dir);
        $this->tempDirs[] = $dir;
        return $dir;
    }

    private function writeFile(string $path, string $contents): void
    {
        file_put_contents($path, $contents);
        $this->tempFiles = array_merge($this->tempFiles, [$path]);
    }
}
