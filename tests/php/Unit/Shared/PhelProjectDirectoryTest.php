<?php

declare(strict_types=1);

namespace PhelTest\Unit\Shared;

use Phel\Shared\PhelProjectDirectory;
use PHPUnit\Framework\TestCase;

use const DIRECTORY_SEPARATOR;

final class PhelProjectDirectoryTest extends TestCase
{
    private const string SEP = DIRECTORY_SEPARATOR;

    protected function setUp(): void
    {
        putenv(PhelProjectDirectory::DIR_ENV);
    }

    protected function tearDown(): void
    {
        putenv(PhelProjectDirectory::DIR_ENV);
    }

    public function test_path_without_subpath_defaults_to_dot_phel(): void
    {
        self::assertSame(
            '/project' . self::SEP . '.phel',
            PhelProjectDirectory::path('/project'),
        );
    }

    public function test_path_with_subpath_is_appended(): void
    {
        self::assertSame(
            '/project' . self::SEP . '.phel' . self::SEP . 'cache',
            PhelProjectDirectory::path('/project', 'cache'),
        );
    }

    public function test_path_strips_leading_separators_from_subpath(): void
    {
        self::assertSame(
            '/project' . self::SEP . '.phel' . self::SEP . 'cache',
            PhelProjectDirectory::path('/project', '/cache'),
        );
    }

    public function test_configured_dir_overrides_default(): void
    {
        self::assertSame(
            '/project' . self::SEP . 'custom-dir',
            PhelProjectDirectory::path('/project', '', 'custom-dir'),
        );
    }

    public function test_env_var_overrides_configured_dir(): void
    {
        putenv(PhelProjectDirectory::DIR_ENV . '=env-dir');

        self::assertSame(
            '/project' . self::SEP . 'env-dir',
            PhelProjectDirectory::path('/project', '', 'configured-dir'),
        );
    }

    public function test_absolute_configured_dir_ignores_project_root(): void
    {
        self::assertSame(
            '/absolute/state',
            PhelProjectDirectory::path('/project', '', '/absolute/state'),
        );
    }

    public function test_resolve_returns_empty_for_empty_config_path(): void
    {
        self::assertSame('', PhelProjectDirectory::resolve('/project', ''));
    }

    public function test_resolve_returns_absolute_path_unchanged(): void
    {
        self::assertSame('/abs/log.txt', PhelProjectDirectory::resolve('/project', '/abs/log.txt'));
    }

    public function test_resolve_rewrites_dot_phel_prefix_through_state_dir(): void
    {
        self::assertSame(
            '/project' . self::SEP . '.phel' . self::SEP . 'cache',
            PhelProjectDirectory::resolve('/project', '.phel/cache'),
        );
    }

    public function test_resolve_bare_dot_phel_maps_to_state_dir(): void
    {
        self::assertSame(
            '/project' . self::SEP . '.phel',
            PhelProjectDirectory::resolve('/project', '.phel'),
        );
    }

    public function test_resolve_relative_path_joined_to_project_root(): void
    {
        self::assertSame(
            '/project' . self::SEP . 'data/out',
            PhelProjectDirectory::resolve('/project', 'data/out'),
        );
    }

    public function test_resolve_dot_phel_prefix_honours_configured_dir(): void
    {
        self::assertSame(
            '/project' . self::SEP . 'custom' . self::SEP . 'cache',
            PhelProjectDirectory::resolve('/project', '.phel/cache', 'custom'),
        );
    }

    public function test_phar_uri_is_treated_as_absolute(): void
    {
        self::assertSame(
            'phar:///app/phel.phar/state',
            PhelProjectDirectory::resolve('/project', 'phar:///app/phel.phar/state'),
        );
    }
}
