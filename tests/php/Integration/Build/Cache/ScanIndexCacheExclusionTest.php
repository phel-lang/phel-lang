<?php

declare(strict_types=1);

namespace PhelTest\Integration\Build\Cache;

use Phel;
use PhelTest\Support\RemoveDirTrait;
use PhelTest\Support\Subprocess;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function dirname;

final class ScanIndexCacheExclusionTest extends TestCase
{
    use RemoveDirTrait;

    private string $project;

    protected function setUp(): void
    {
        $this->project = realpath(sys_get_temp_dir()) . '/phel-scan-cache-exclusion-' . bin2hex(random_bytes(6));
        mkdir($this->project . '/src', 0755, true);
        mkdir($this->project . '/cache/src', 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->project);
    }

    public function test_the_active_cache_subtree_is_excluded_from_source_discovery(): void
    {
        file_put_contents($this->project . '/src/main.phel', '(ns app.main)');
        file_put_contents($this->project . '/cache/obsolete.phel', '(ns app.obsolete)');
        $this->configure('src', 'cache');

        self::assertSame(['app.main'], $this->scan());
    }

    public static function overlappingDirectories(): iterable
    {
        yield 'cache is source root' => ['cache', 'cache'];
        yield 'cache contains source root' => ['cache/src', 'cache'];
        yield 'cache is project root' => ['src', '.'];
    }

    public function test_a_cache_symlink_to_a_configured_source_does_not_hide_it(): void
    {
        file_put_contents($this->project . '/src/main.phel', '(ns app.main)');
        symlink($this->project . '/src', $this->project . '/cache-alias');
        $this->configure('src', 'cache-alias');

        self::assertSame(['app.main'], $this->scan());
    }

    #[DataProvider('overlappingDirectories')]
    public function test_a_cache_that_contains_a_configured_source_is_not_excluded(string $source, string $cache): void
    {
        file_put_contents($this->project . '/' . $source . '/main.phel', '(ns app.main)');
        $this->configure($source, $cache);

        self::assertSame(['app.main'], $this->scan());
    }

    public static function overlappingTestDirectories(): iterable
    {
        yield 'cache is test root' => ['tests', 'tests'];
        yield 'cache contains test root' => ['cache/tests', 'cache'];
    }

    #[DataProvider('overlappingTestDirectories')]
    public function test_a_cache_that_contains_a_configured_test_root_does_not_hide_tests(string $tests, string $cache): void
    {
        mkdir($this->project . '/' . $tests, 0755, true);
        file_put_contents($this->project . '/' . $tests . '/main.phel', '(ns app.main-test)');
        $this->configure('src', $cache, [$tests]);

        self::assertSame(['app.main-test'], $this->scan(configuredRoots: true));
    }

    public static function overlappingVendorDirectories(): iterable
    {
        yield 'cache is vendor source root' => ['vendor/acme/library/src'];
        yield 'cache contains vendor source root' => ['vendor/acme'];
    }

    #[DataProvider('overlappingVendorDirectories')]
    public function test_a_cache_that_contains_a_vendor_source_root_does_not_hide_dependencies(string $cache): void
    {
        mkdir($this->project . '/vendor/acme/library/src', 0755, true);
        file_put_contents($this->project . '/vendor/acme/library/src/library.phel', '(ns app.library)');
        file_put_contents($this->project . '/vendor/acme/library/phel-config.php', "<?php return new \\Phel\\Config\\PhelConfig()->withSrcDirs(['src']);");
        $this->configure('src', $cache, vendor: 'vendor');

        self::assertSame(['app.library'], $this->scan(configuredRoots: true));
    }

    private function configure(string $source, string $cache, array $tests = [], string $vendor = ''): void
    {
        file_put_contents($this->project . '/phel-config.php', '<?php return new \\Phel\\Config\\PhelConfig()'
            . '->withSrcDirs([' . var_export($source, true) . '])->withTestDirs(' . var_export($tests, true) . ')'
            . '->withVendorDir(' . var_export($vendor, true) . ')'
            . '->withCacheDir(__DIR__ . ' . var_export('/' . $cache, true) . ');');
    }

    private function scan(bool $configuredRoots = false): array
    {
        $roots = $configuredRoots ? '(new \\Phel\\Command\\CommandFacade())->getAllPhelDirectories()' : '[getcwd()]';
        $code = 'require ' . var_export(dirname(__DIR__, 5) . '/vendor/autoload.php', true) . ';'
            . (Phel::class . '::bootstrap(getcwd());')
            . '$infos = (new \\Phel\\Build\\BuildFactory())->createNamespaceExtractor()->getNamespacesFromDirectories(' . $roots . ');'
            . '$names = array_map(static fn($info) => $info->getNamespace(), $infos);'
            . '$names = array_values(array_filter($names, static fn($name) => str_starts_with($name, "app.")));'
            . 'sort($names); echo json_encode($names);';
        $result = Subprocess::run([PHP_BINARY, '-r', $code], $this->project);
        self::assertSame(0, $result->exitCode, $result->stderr);

        return json_decode($result->stdout, true, flags: JSON_THROW_ON_ERROR);
    }
}
