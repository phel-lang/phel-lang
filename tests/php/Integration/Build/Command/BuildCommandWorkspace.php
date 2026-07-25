<?php

declare(strict_types=1);

namespace PhelTest\Integration\Build\Command;

use FilesystemIterator;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use PhelTest\Integration\Util\DirectoryUtil;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function dirname;
use function file_put_contents;
use function getmypid;
use function sprintf;
use function sys_get_temp_dir;
use function uniqid;
use function var_export;

/**
 * Isolated, per-process project root for `bin/phel build` tests.
 *
 * Build tests must never write their compiled output tree inside the repo:
 * NamespaceLoader scans getcwd() (the repo root), so a sibling paratest worker
 * mid-build would surface this test's `out/phel/core.phel` as a duplicate of
 * the real `src/phel/core.phel` and load the wrong file. Building under
 * sys_get_temp_dir() — already worker-private via the paratest bootstrap —
 * keeps the output, and any fixture the test mutates, off every scan root.
 */
final readonly class BuildCommandWorkspace
{
    private const string COMMAND_DIR = __DIR__;

    private string $root;

    /**
     * @param bool $shared keep the same root across a class's process-isolated
     *                     methods (needed for `#[Depends]` chains); within a
     *                     worker the label is unique, across workers TMPDIR is
     */
    public function __construct(string $label, bool $shared = false)
    {
        $base = sys_get_temp_dir() . '/phel-build-' . $label;
        $this->root = $shared ? $base : $base . '-' . getmypid() . '-' . uniqid();
        @mkdir($this->root, 0o777, true);
    }

    public function root(): string
    {
        return $this->root;
    }

    public function path(string $relative): string
    {
        return $this->root . '/' . $relative;
    }

    /**
     * Copy a fixture file or directory from the Command dir into the same
     * relative location under the workspace root.
     */
    public function import(string $relative): self
    {
        DirectoryUtil::copyPath(self::COMMAND_DIR . '/' . $relative, $this->root . '/' . $relative);

        return $this;
    }

    public function writeFile(string $relative, string $contents): self
    {
        $target = $this->root . '/' . $relative;
        @mkdir(dirname($target), 0o777, true);
        file_put_contents($target, $contents);

        return $this;
    }

    /**
     * Bootstrap Gacela against this workspace with one of its imported fixture
     * configs as the app config.
     */
    public function bootstrapGacela(string $appConfig): void
    {
        Gacela::bootstrap($this->root, static function (GacelaConfig $config) use ($appConfig): void {
            $config->addAppConfig($appConfig);
        });
    }

    /**
     * Write a standalone `run.php` next to the build output that requires the
     * repo autoloader plus one compiled artifact, and return its path. Running
     * it proves the artifact needs no source tree.
     *
     * @param string $destRelative     build output dir, relative to the workspace root
     * @param string $compiledRelative compiled entry point, relative to $destRelative
     */
    public function writeRunner(string $destRelative, string $compiledRelative): string
    {
        $destDir = $this->path($destRelative);
        $runner = $destDir . '/run.php';

        $code = sprintf(
            "<?php declare(strict_types=1);\nrequire_once %s;\nrequire_once %s;\n",
            var_export(dirname(self::COMMAND_DIR, 5) . '/vendor/autoload.php', true),
            var_export($destDir . '/' . $compiledRelative, true),
        );
        file_put_contents($runner, $code);

        return $runner;
    }

    /**
     * Every compiled `.php` artifact under a build output dir.
     *
     * @return iterable<string>
     */
    public function compiledPhpFiles(string $destRelative): iterable
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->path($destRelative), FilesystemIterator::SKIP_DOTS),
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                yield $file->getPathname();
            }
        }
    }

    public function remove(): void
    {
        DirectoryUtil::removeDir($this->root);
    }
}
