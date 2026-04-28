<?php

declare(strict_types=1);

namespace PhelTest\Unit\Command\Application;

use Phel\Command\Application\DirectoryFinder;
use Phel\Command\Domain\CodeDirectories;
use Phel\Command\Domain\Finder\VendorDirectoriesFinderInterface;
use PHPUnit\Framework\TestCase;

final class DirectoryFinderTest extends TestCase
{
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
