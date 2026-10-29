<?php

declare(strict_types=1);

namespace PhelTest\Integration\Build\Command;

use PhelTest\Support\RemoveDirTrait;
use PhelTest\Support\Subprocess;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function dirname;

final class NamespaceCollisionBuildTest extends TestCase
{
    use RemoveDirTrait;

    private string $project;

    protected function setUp(): void
    {
        $this->project = realpath(sys_get_temp_dir()) . '/phel-namespace-collision-' . bin2hex(random_bytes(6));
        mkdir($this->project . '/src', 0755, true);
        mkdir($this->project . '/vendor');
        mkdir($this->project . '/out/a', 0755, true);
        file_put_contents($this->project . '/vendor/autoload.php', '<?php return require ' . var_export(dirname(__DIR__, 5) . '/vendor/autoload.php', true) . ';');
        file_put_contents($this->project . '/phel-config.php', <<<'PHP'
<?php return new \Phel\Config\PhelConfig()
    ->withSrcDirs(['src'])->withTestDirs([])->withVendorDir('')
    ->withTempDir(__DIR__ . '/tmp')->withCacheDir(__DIR__ . '/cache');
PHP);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->project);
    }

    public function test_build_allows_fragments_of_the_same_namespace(): void
    {
        file_put_contents($this->project . '/src/first.phel', '(ns a.normal) (def x 1) (load "second")');
        file_put_contents($this->project . '/src/second.phel', '(in-ns a.normal) (def y 2)');

        $result = Subprocess::run([PHP_BINARY, dirname(__DIR__, 5) . '/bin/phel', 'build'], $this->project);

        self::assertSame(0, $result->exitCode, $result->stdout . $result->stderr);
        self::assertFileExists($this->project . '/out/a/normal.php');
        self::assertFileExists($this->project . '/out/a/second.php');
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function collisions(): iterable
    {
        yield 'dash and underscore' => ['a.foo-bar', 'a.foo_bar', 'foo_bar'];
        yield 'PHP namespace case' => ['a.Foo', 'a.foo', 'foo'];
    }

    #[DataProvider('collisions')]
    public function test_build_refuses_namespace_collisions_before_writing_output(string $first, string $second, string $target): void
    {
        file_put_contents($this->project . '/src/first.phel', '(ns ' . $first . ')');
        file_put_contents($this->project . '/src/second.phel', '(ns ' . $second . ')');
        $artifact = $this->project . '/out/a/' . $target . '.php';
        file_put_contents($artifact, '<?php /* existing build output */');

        $result = Subprocess::run([PHP_BINARY, dirname(__DIR__, 5) . '/bin/phel', 'build'], $this->project);

        self::assertSame(1, $result->exitCode, $result->stdout . $result->stderr);
        $message = $result->stdout . $result->stderr;
        self::assertStringContainsString($first, $message);
        self::assertStringContainsString($second, $message);
        self::assertStringContainsString('first.phel', $message);
        self::assertStringContainsString('second.phel', $message);
        self::assertSame('<?php /* existing build output */', file_get_contents($artifact));
        self::assertFileDoesNotExist($this->project . '/out/phel/core.php');
    }
}
