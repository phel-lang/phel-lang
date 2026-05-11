<?php

declare(strict_types=1);

namespace PhelTest\Unit\Filesystem\Application;

use Phel\Compiler\Domain\Evaluator\Exceptions\FileException;
use Phel\Filesystem\Application\PhelProjectDirectory;
use PHPUnit\Framework\TestCase;

final class PhelProjectDirectoryTest extends TestCase
{
    private string $projectRoot = '';

    protected function setUp(): void
    {
        $this->projectRoot = sys_get_temp_dir() . '/phel-proj-' . uniqid('', true);
        mkdir($this->projectRoot);
    }

    protected function tearDown(): void
    {
        $phelDir = $this->projectRoot . '/' . PhelProjectDirectory::DIRECTORY_NAME;
        $gitignore = $phelDir . '/.gitignore';
        if (is_file($gitignore)) {
            unlink($gitignore);
        }

        if (is_dir($phelDir)) {
            rmdir($phelDir);
        }

        if (is_dir($this->projectRoot)) {
            rmdir($this->projectRoot);
        }
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

    public function test_throws_when_directory_cannot_be_created(): void
    {
        $unwritable = sys_get_temp_dir() . '/phel-unwritable-' . uniqid('', true);
        mkdir($unwritable, 0o555);

        try {
            $this->expectException(FileException::class);
            PhelProjectDirectory::ensure($unwritable);
        } finally {
            chmod($unwritable, 0o755);
            rmdir($unwritable);
        }
    }
}
