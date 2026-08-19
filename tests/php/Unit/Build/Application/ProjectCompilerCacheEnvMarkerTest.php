<?php

declare(strict_types=1);

namespace PhelTest\Unit\Build\Application;

use Override;
use Phel\Build\Application\ProjectCompiler;
use Phel\Build\BuildConfigInterface;
use Phel\Build\Domain\Compile\BuildOptions;
use Phel\Build\Domain\Compile\CompiledSecondaryStore;
use Phel\Build\Domain\Compile\CompiledTargetPathResolver;
use Phel\Build\Domain\Compile\FileCompilerInterface;
use Phel\Build\Domain\Compile\Output\EntryPointPhpFileInterface;
use Phel\Build\Domain\Compile\SecondaryFileHarvester;
use Phel\Build\Domain\Extractor\NamespaceExtractorInterface;
use Phel\Build\Domain\IO\FileContentsIoInterface;
use Phel\Build\Infrastructure\IO\SystemFileIo;
use Phel\Shared\Facade\CommandFacadeInterface;
use Phel\Shared\Facade\CompilerFacadeInterface;
use PhelTest\Support\RemoveDirTrait;
use PHPUnit\Framework\TestCase;

use function file_get_contents;
use function file_put_contents;
use function mkdir;
use function sys_get_temp_dir;
use function uniqid;

/**
 * The output tree records the fingerprint of the declared `cache-env-vars`, so
 * `phel build`'s mtime-only reuse check recompiles when the environment behind
 * a macro expansion changed (#3236).
 */
final class ProjectCompilerCacheEnvMarkerTest extends TestCase
{
    use RemoveDirTrait;

    private const string MARKER = '/.phel-cache-env-fingerprint';

    private string $destDir;

    #[Override]
    protected function setUp(): void
    {
        $this->destDir = sys_get_temp_dir() . '/phel-marker-' . uniqid();
        mkdir($this->destDir, 0o755, true);
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->removeDir($this->destDir);
    }

    public function test_a_declared_fingerprint_is_recorded_next_to_the_output(): void
    {
        $this->compilerWithFingerprint('fingerprint-a')->compileProject(new BuildOptions(enableCache: false, enableSourceMap: false));

        self::assertFileExists($this->destDir . self::MARKER);
        self::assertSame('fingerprint-a', file_get_contents($this->destDir . self::MARKER));
    }

    public function test_declaring_nothing_leaves_no_marker(): void
    {
        $this->compilerWithFingerprint('')->compileProject(new BuildOptions(enableCache: false, enableSourceMap: false));

        self::assertFileDoesNotExist($this->destDir . self::MARKER);
    }

    public function test_dropping_the_config_key_removes_the_stale_marker(): void
    {
        file_put_contents($this->destDir . self::MARKER, 'fingerprint-a');

        $this->compilerWithFingerprint('')->compileProject(new BuildOptions(enableCache: false, enableSourceMap: false));

        self::assertFileDoesNotExist($this->destDir . self::MARKER);
    }

    private function compilerWithFingerprint(string $fingerprint): ProjectCompiler
    {
        $namespaceExtractor = $this->createStub(NamespaceExtractorInterface::class);
        $namespaceExtractor->method('getNamespacesFromDirectories')->willReturn([]);

        $commandFacade = $this->createStub(CommandFacadeInterface::class);
        $commandFacade->method('getSourceDirectories')->willReturn([]);
        $commandFacade->method('getVendorSourceDirectories')->willReturn([]);
        $commandFacade->method('getOutputDirectory')->willReturn($this->destDir);

        $compilerFacade = $this->createStub(CompilerFacadeInterface::class);

        return new ProjectCompiler(
            $namespaceExtractor,
            $this->createStub(FileCompilerInterface::class),
            $compilerFacade,
            $commandFacade,
            $this->createStub(EntryPointPhpFileInterface::class),
            $this->createStub(BuildConfigInterface::class),
            new SecondaryFileHarvester(
                new CompiledTargetPathResolver($compilerFacade),
                $this->fileIo(),
                new CompiledSecondaryStore(),
            ),
            $fingerprint,
        );
    }

    private function fileIo(): FileContentsIoInterface
    {
        return new SystemFileIo();
    }
}
