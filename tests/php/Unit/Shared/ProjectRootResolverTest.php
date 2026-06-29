<?php

declare(strict_types=1);

namespace PhelTest\Unit\Shared;

use Phel\Shared\ProjectRootResolver;
use PHPUnit\Framework\TestCase;

use function bin2hex;
use function mkdir;
use function random_bytes;
use function sys_get_temp_dir;

final class ProjectRootResolverTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/phel-root-' . bin2hex(random_bytes(6));
        mkdir($this->root, 0755, true);
    }

    protected function tearDown(): void
    {
        @unlink($this->root . '/phel-config.php');
        @unlink($this->root . '/a/phel-config.php');
        @rmdir($this->root . '/a/b');
        @rmdir($this->root . '/a');
        @rmdir($this->root);
    }

    public function test_returns_cwd_when_it_holds_the_config(): void
    {
        $this->writeConfig($this->root);

        self::assertSame($this->root, ProjectRootResolver::resolveFromCwd($this->root));
    }

    public function test_walks_up_to_the_nearest_config_from_a_subdirectory(): void
    {
        $this->writeConfig($this->root);
        $deep = $this->root . '/a/b';
        mkdir($deep, 0755, true);

        self::assertSame($this->root, ProjectRootResolver::resolveFromCwd($deep));
    }

    public function test_falls_back_to_cwd_when_no_config_is_found(): void
    {
        // No phel-config.php anywhere up the tree from this fresh temp dir.
        self::assertSame($this->root, ProjectRootResolver::resolveFromCwd($this->root));
    }

    public function test_prefers_the_nearest_config_over_an_ancestor(): void
    {
        $this->writeConfig($this->root);
        $nearer = $this->root . '/a';
        mkdir($nearer, 0755, true);
        $this->writeConfig($nearer);
        $deep = $nearer . '/b';
        mkdir($deep, 0755, true);

        self::assertSame($nearer, ProjectRootResolver::resolveFromCwd($deep));
    }

    public function test_walk_terminates_at_the_filesystem_root(): void
    {
        // No phel-config.php at '/', so the walk must hit the dirname('/') === '/'
        // guard and return cwd instead of looping forever.
        self::assertSame('/', ProjectRootResolver::resolveFromCwd('/'));
    }

    private function writeConfig(string $dir): void
    {
        file_put_contents($dir . '/phel-config.php', "<?php\n\nreturn null;\n");
    }
}
