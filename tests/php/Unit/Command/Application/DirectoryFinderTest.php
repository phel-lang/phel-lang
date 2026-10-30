<?php

declare(strict_types=1);

namespace PhelTest\Unit\Command\Application;

use Phel\Command\Application\DirectoryFinder;
use Phel\Command\Domain\CodeDirectories;
use Phel\Command\Domain\Finder\VendorDirectoriesFinderInterface;
use PhelTest\Support\RemoveDirTrait;
use PHPUnit\Framework\TestCase;

final class DirectoryFinderTest extends TestCase
{
    use RemoveDirTrait;

    public function test_relative_directories_resolve_from_the_project_root(): void
    {
        $root = realpath(sys_get_temp_dir()) . '/phel-project-dirs-' . bin2hex(random_bytes(6));
        $cwd = getcwd();
        self::assertIsString($cwd);
        foreach (['src', 'tests', 'sub/src', 'sub/tests'] as $dir) {
            mkdir($root . '/' . $dir, 0755, true);
        }

        try {
            chdir($root . '/sub');
            $vendorFinder = $this->createStub(VendorDirectoriesFinderInterface::class);
            $vendorFinder->method('findPhelSourceDirectories')->willReturn([]);
            $codeDirs = new CodeDirectories('phar://phel.phar/src', ['src', './src', $root . '/src'], ['tests'], 'out');
            $finder = new DirectoryFinder($root, $codeDirs, $vendorFinder);

            self::assertSame([$root . '/src'], $finder->getProjectSourceDirectories());
            self::assertSame(['phar://phel.phar/src', $root . '/src'], $finder->getSourceDirectories());
            self::assertSame([$root . '/tests'], $finder->getTestDirectories());
        } finally {
            chdir($cwd);
            $this->removeDir($root);
        }
    }

    public function test_a_missing_absolute_directory_is_not_prefixed(): void
    {
        $absolute = realpath(sys_get_temp_dir()) . '/phel-missing-' . bin2hex(random_bytes(6));
        $vendorFinder = $this->createStub(VendorDirectoriesFinderInterface::class);
        $vendorFinder->method('findPhelSourceDirectories')->willReturn([]);
        $codeDirs = new CodeDirectories('phar://phel.phar/src', [$absolute], [], 'out');
        $finder = new DirectoryFinder('/project', $codeDirs, $vendorFinder);

        self::assertSame([$absolute], $finder->getProjectSourceDirectories());
    }

    public function test_phar_paths_are_not_prefixed(): void
    {
        $vendorFinder = $this->createStub(VendorDirectoriesFinderInterface::class);
        $vendorFinder->method('findPhelSourceDirectories')->willReturn([]);

        $codeDirs = new CodeDirectories('phar://phel.phar/src', ['phar://phel.phar/src'], [], 'out');
        $finder = new DirectoryFinder('/project', $codeDirs, $vendorFinder);

        self::assertSame(['phar://phel.phar/src'], $finder->getSourceDirectories());
    }

    public function test_project_source_directories_exclude_phel_internal_dir(): void
    {
        $vendorFinder = $this->createStub(VendorDirectoriesFinderInterface::class);
        $vendorFinder->method('findPhelSourceDirectories')->willReturn([]);

        $codeDirs = new CodeDirectories(
            'phar://phel.phar/phel-internal',
            ['phar://phel.phar/src'],
            [],
            'out',
        );
        $finder = new DirectoryFinder('/project', $codeDirs, $vendorFinder);

        self::assertSame(
            ['phar://phel.phar/phel-internal', 'phar://phel.phar/src'],
            $finder->getSourceDirectories(),
        );
        self::assertSame(
            ['phar://phel.phar/src'],
            $finder->getProjectSourceDirectories(),
        );
    }
}
