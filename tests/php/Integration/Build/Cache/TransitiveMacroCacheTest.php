<?php

declare(strict_types=1);

namespace PhelTest\Integration\Build\Cache;

use PhelTest\Support\RemoveDirTrait;
use PhelTest\Support\Subprocess;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function dirname;

final class TransitiveMacroCacheTest extends TestCase
{
    use RemoveDirTrait;

    private string $project;

    protected function setUp(): void
    {
        $this->project = realpath(sys_get_temp_dir()) . '/phel-transitive-cache-' . bin2hex(random_bytes(6));
        mkdir($this->project . '/src', 0755, true);
        mkdir($this->project . '/vendor');
        file_put_contents($this->project . '/vendor/autoload.php', '<?php return require ' . var_export(dirname(__DIR__, 5) . '/vendor/autoload.php', true) . ';');
        file_put_contents($this->project . '/phel-config.php', <<<'PHP'
<?php return new \Phel\Config\PhelConfig()
    ->withSrcDirs(['src'])->withTestDirs([])->withVendorDir('')
    ->withTempDir(__DIR__ . '/tmp')->withCacheDir(__DIR__ . '/cache');
PHP);
        file_put_contents($this->project . '/src/a.phel', "(ns app.a (:require app.b :as b) (:require app.unrelated))\n(println (b/value))\n");
        file_put_contents($this->project . '/src/b.phel', "(ns app.b (:require app.c :as c))\n(defmacro value [] (c/value))\n");
        file_put_contents($this->project . '/src/unrelated.phel', "(ns app.unrelated)\n(def x 42)\n");
        $this->writeMacro(1);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->project);
    }

    /**
     * @return iterable<string, array{bool}>
     */
    public static function entrypointOrders(): iterable
    {
        yield 'dependent entrypoint directly' => [false];
        yield 'changed dependency in earlier process' => [true];
    }

    #[DataProvider('entrypointOrders')]
    public function test_transitive_macro_change_recompiles_a_warm_dependent(bool $runDependencyFirst): void
    {
        self::assertSame("1\n", $this->runMain());
        $mainCache = $this->compiledPath('a');
        $unrelatedCache = $this->compiledPath('unrelated');
        touch($mainCache, 1);
        touch($unrelatedCache, 1);
        self::assertSame("1\n", $this->runMain());
        clearstatcache();
        self::assertSame(1, filemtime($mainCache));

        $this->writeMacro(2);
        if ($runDependencyFirst) {
            $changedDependency = Subprocess::run([PHP_BINARY, dirname(__DIR__, 5) . '/bin/phel', 'run', 'src/c.phel'], $this->project);
            self::assertSame(0, $changedDependency->exitCode, $changedDependency->stderr);
        }

        self::assertSame("2\n", $this->runMain());
        clearstatcache();
        self::assertNotSame(1, filemtime($mainCache));
        self::assertSame(1, filemtime($unrelatedCache));

        touch($mainCache, 1);
        self::assertSame("2\n", $this->runMain());
        clearstatcache();
        self::assertSame(1, filemtime($mainCache));
    }

    private function compiledPath(string $name): string
    {
        $index = include $this->project . '/cache/compiled-index.php';

        return $index['entries'][$this->project . '/src/' . $name . '.phel']['compiled_path'];
    }

    private function writeMacro(int $value): void
    {
        file_put_contents($this->project . '/src/c.phel', "(ns app.c)\n(defmacro value [] " . $value . ")\n");
        touch($this->project . '/src/c.phel', time() - 5);
    }

    private function runMain(): string
    {
        $result = Subprocess::run([PHP_BINARY, dirname(__DIR__, 5) . '/bin/phel', 'run', 'src/a.phel'], $this->project);
        self::assertSame(0, $result->exitCode, $result->stderr);

        return $result->stdout;
    }
}
