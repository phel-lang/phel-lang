<?php

declare(strict_types=1);

namespace PhelTest\Unit\Filesystem\Application;

use Phel\Shared\PhelProjectDirectory;
use PHPUnit\Framework\TestCase;

final class PhelProjectDirectoryTest extends TestCase
{
    private string $projectRoot = '';

    protected function setUp(): void
    {
        $this->projectRoot = sys_get_temp_dir() . '/phel-proj-' . uniqid('', true);
        mkdir($this->projectRoot);
        putenv(PhelProjectDirectory::DIR_ENV);
    }

    protected function tearDown(): void
    {
        putenv(PhelProjectDirectory::DIR_ENV);
        $this->removeTree($this->projectRoot);
    }

    public function test_creates_directory_and_seeds_gitignore_on_first_call(): void
    {
        $dir = PhelProjectDirectory::ensure($this->projectRoot);

        self::assertSame($this->projectRoot . '/' . PhelProjectDirectory::DIRECTORY_NAME, $dir);
        self::assertDirectoryExists($dir);

        self::assertSame(
            "# Created automatically by Phel.\n*\n",
            file_get_contents($dir . '/.gitignore'),
        );
    }

    public function test_preserves_existing_gitignore_content(): void
    {
        $dir = $this->projectRoot . '/' . PhelProjectDirectory::DIRECTORY_NAME;
        mkdir($dir);
        file_put_contents($dir . '/.gitignore', "# user edit\nfoo\n");

        PhelProjectDirectory::ensure($this->projectRoot);

        self::assertSame("# user edit\nfoo\n", file_get_contents($dir . '/.gitignore'));
    }

    public function test_idempotent_when_directory_already_exists(): void
    {
        PhelProjectDirectory::ensure($this->projectRoot);
        $dir = PhelProjectDirectory::ensure($this->projectRoot);

        self::assertDirectoryExists($dir);
        self::assertSame(
            "# Created automatically by Phel.\n*\n",
            file_get_contents($dir . '/.gitignore'),
        );
    }

    public function test_strips_trailing_separator_from_project_root(): void
    {
        $dir = PhelProjectDirectory::ensure($this->projectRoot . '/');

        self::assertSame($this->projectRoot . '/' . PhelProjectDirectory::DIRECTORY_NAME, $dir);
    }

    public function test_best_effort_returns_path_when_directory_cannot_be_created(): void
    {
        $unwritable = sys_get_temp_dir() . '/phel-unwritable-' . uniqid('', true);
        mkdir($unwritable, 0o555);

        try {
            $dir = @PhelProjectDirectory::ensure($unwritable);

            self::assertSame(
                $unwritable . '/' . PhelProjectDirectory::DIRECTORY_NAME,
                $dir,
            );
            self::assertDirectoryDoesNotExist($dir);
        } finally {
            chmod($unwritable, 0o755);
            rmdir($unwritable);
        }
    }

    public function test_env_var_relocates_directory_to_absolute_path(): void
    {
        $relocated = sys_get_temp_dir() . '/phel-elsewhere-' . uniqid('', true);
        putenv(PhelProjectDirectory::DIR_ENV . '=' . $relocated);

        try {
            $dir = PhelProjectDirectory::ensure($this->projectRoot);

            self::assertSame($relocated, $dir);
            self::assertDirectoryExists($relocated);
            self::assertDirectoryDoesNotExist($this->projectRoot . '/' . PhelProjectDirectory::DIRECTORY_NAME);
        } finally {
            $this->removeTree($relocated);
        }
    }

    public function test_path_with_configured_dir_resolves_against_project_root(): void
    {
        $dir = PhelProjectDirectory::path($this->projectRoot, 'cache', 'state');

        self::assertSame($this->projectRoot . '/state/cache', $dir);
    }

    public function test_env_wins_over_configured_dir(): void
    {
        $relocated = sys_get_temp_dir() . '/phel-env-wins-' . uniqid('', true);
        putenv(PhelProjectDirectory::DIR_ENV . '=' . $relocated);

        try {
            $dir = PhelProjectDirectory::path($this->projectRoot, '', '/ignored/configured');

            self::assertSame($relocated, $dir);
        } finally {
            if (is_dir($relocated)) {
                rmdir($relocated);
            }
        }
    }

    public function test_resolve_rewrites_phel_prefixed_config_paths(): void
    {
        putenv(PhelProjectDirectory::DIR_ENV . '=/foo');

        try {
            self::assertSame('/foo/cache', PhelProjectDirectory::resolve($this->projectRoot, '.phel/cache'));
            self::assertSame('/foo', PhelProjectDirectory::resolve($this->projectRoot, '.phel'));
            self::assertSame('/etc/phel-cache', PhelProjectDirectory::resolve($this->projectRoot, '/etc/phel-cache'));
            self::assertSame(
                $this->projectRoot . '/custom-cache',
                PhelProjectDirectory::resolve($this->projectRoot, 'custom-cache'),
            );
        } finally {
            putenv(PhelProjectDirectory::DIR_ENV);
        }
    }

    private function removeTree(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }

        if (is_file($path) || is_link($path)) {
            unlink($path);
            return;
        }

        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.') {
                continue;
            }

            if ($entry === '..') {
                continue;
            }

            $this->removeTree($path . '/' . $entry);
        }

        rmdir($path);
    }
}
