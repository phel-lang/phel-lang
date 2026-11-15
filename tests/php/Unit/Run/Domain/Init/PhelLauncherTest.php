<?php

declare(strict_types=1);

namespace PhelTest\Unit\Run\Domain\Init;

use Phel\Run\Domain\Init\PhelLauncher;
use PHPUnit\Framework\TestCase;

final class PhelLauncherTest extends TestCase
{
    public function test_composer_install_runs_the_vendor_binary(): void
    {
        $launcher = PhelLauncher::composer();

        self::assertFalse($launcher->isPhar());
        self::assertSame('./vendor/bin/phel', $launcher->command('/any/project'));
    }

    public function test_an_empty_phar_path_means_composer(): void
    {
        self::assertFalse(PhelLauncher::fromPharPath('')->isPhar());
    }

    public function test_a_phar_inside_the_project_is_named_relative_to_it(): void
    {
        $launcher = PhelLauncher::fromPharPath('/work/app/tools/phel.phar');

        self::assertTrue($launcher->isPhar());
        self::assertSame('php tools/phel.phar', $launcher->command('/work/app'));
    }

    public function test_a_phar_outside_the_project_keeps_its_absolute_path(): void
    {
        self::assertSame(
            'php /opt/bin/phel.phar',
            PhelLauncher::fromPharPath('/opt/bin/phel.phar')->command('/work/app'),
        );
    }

    public function test_a_sibling_directory_with_the_same_prefix_is_not_inside_the_project(): void
    {
        self::assertSame(
            'php /work/app2/phel.phar',
            PhelLauncher::fromPharPath('/work/app2/phel.phar')->command('/work/app'),
        );
    }

    public function test_a_path_with_spaces_is_quoted_for_the_shell(): void
    {
        self::assertSame(
            "php '/my tools/phel.phar'",
            PhelLauncher::fromPharPath('/my tools/phel.phar')->command('/work/app'),
        );
    }

    public function test_a_relative_path_with_spaces_is_quoted_for_the_shell(): void
    {
        self::assertSame(
            "php 'my tools/phel.phar'",
            PhelLauncher::fromPharPath('/work/app/my tools/phel.phar')->command('/work/app'),
        );
    }

    public function test_a_relative_name_starting_with_a_dash_is_not_read_as_an_option(): void
    {
        self::assertSame(
            'php ./-x.phar',
            PhelLauncher::fromPharPath('/work/app/-x.phar')->command('/work/app'),
        );
    }

    public function test_a_phar_in_the_filesystem_root_keeps_a_single_slash(): void
    {
        self::assertSame('php /phel.phar', PhelLauncher::fromPharPath('/phel.phar')->command('/work/app'));
    }

    public function test_a_backslash_in_a_posix_directory_name_is_kept(): void
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            self::markTestSkipped('a backslash is a separator on Windows');
        }

        self::assertSame(
            "php 'tools\\old/phel.phar'",
            PhelLauncher::fromPharPath('/work/app/tools\\old/phel.phar')->command('/work/app'),
        );
    }

    public function test_a_phar_installed_on_the_path_runs_by_its_name(): void
    {
        $bin = sys_get_temp_dir() . '/phel-launcher-bin-' . bin2hex(random_bytes(4));
        mkdir($bin);
        $bin = (string) realpath($bin);
        touch($bin . '/phel');
        chmod($bin . '/phel', 0755);

        try {
            self::assertSame('phel', PhelLauncher::fromPharPath($bin . '/phel', '/nowhere' . PATH_SEPARATOR . $bin)->command('/work/app'));
            self::assertSame('php ' . $bin . '/phel', PhelLauncher::fromPharPath($bin . '/phel', '/nowhere')->command('/work/app'));

            chmod($bin . '/phel', 0644);
            clearstatcache();
            self::assertSame('php ' . $bin . '/phel', PhelLauncher::fromPharPath($bin . '/phel', $bin)->command('/work/app'));
        } finally {
            unlink($bin . '/phel');
            rmdir($bin);
        }
    }

    public function test_an_earlier_path_entry_with_the_same_name_shadows_the_phar(): void
    {
        $root = sys_get_temp_dir() . '/phel-launcher-shadow-' . bin2hex(random_bytes(4));
        mkdir($root . '/first', 0755, true);
        mkdir($root . '/second');
        $root = (string) realpath($root);
        foreach (['first', 'second'] as $dir) {
            touch($root . '/' . $dir . '/phel');
            chmod($root . '/' . $dir . '/phel', 0755);
        }

        try {
            self::assertSame(
                'php ' . $root . '/second/phel',
                PhelLauncher::fromPharPath($root . '/second/phel', $root . '/first' . PATH_SEPARATOR . $root . '/second')->command('/work/app'),
            );
        } finally {
            foreach (['first', 'second'] as $dir) {
                unlink($root . '/' . $dir . '/phel');
                rmdir($root . '/' . $dir);
            }

            rmdir($root);
        }
    }

    public function test_a_project_reached_through_a_symlink_names_the_phar_relative_to_it(): void
    {
        $real = sys_get_temp_dir() . '/phel-launcher-' . bin2hex(random_bytes(4));
        $link = $real . '-link';
        mkdir($real . '/tools', 0755, true);
        touch($real . '/tools/phel.phar');
        symlink($real, $link);

        try {
            self::assertSame(
                'php tools/phel.phar',
                PhelLauncher::fromPharPath($real . '/tools/phel.phar')->command($link),
            );
        } finally {
            unlink($link);
            unlink($real . '/tools/phel.phar');
            rmdir($real . '/tools');
            rmdir($real);
        }
    }
}
