<?php

declare(strict_types=1);

namespace PhelTest\Integration\Compiler;

use Phel\Config\PhelConfig;
use PhelTest\Support\RemoveDirTrait;
use PhelTest\Support\Subprocess;
use PHPUnit\Framework\TestCase;

use function dirname;

final class ReaderResultCacheIdentityTest extends TestCase
{
    use RemoveDirTrait;

    private string $project;

    protected function setUp(): void
    {
        $this->project = sys_get_temp_dir() . '/phel-reader-identity-' . bin2hex(random_bytes(6));
        mkdir($this->project);
        file_put_contents($this->project . '/phel-config.php', '<?php return ' . PhelConfig::class . '::forProject()->withSrcDirs([])->withTestDirs([])->withEnableIntermediateCache();');
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->project);
    }

    public function test_factory_keys_reader_results_by_the_running_compiler_version(): void
    {
        $result = $this->compileInFreshProcess();

        self::assertTrue($result['found'], 'Reader results must use ' . $result['version']);
        self::assertSame(42, $result['value']);
    }

    public function test_the_same_version_reuses_its_blob_in_a_fresh_process(): void
    {
        $cold = $this->compileInFreshProcess();
        self::assertCount(1, $cold['files']);
        $path = $cold['files'][0];
        self::assertTrue(touch($path, 1));

        $warm = $this->compileInFreshProcess();
        clearstatcache();
        self::assertSame($cold['version'], $warm['version']);
        self::assertSame($cold['files'], $warm['files']);
        self::assertSame(1, filemtime($path), 'A warm reader hit must not rewrite the blob');
    }

    /**
     * @return array<string, mixed>
     */
    private function compileInFreshProcess(): array
    {
        $script = <<<'PHP'
require $argv[1] . '/vendor/autoload.php';
Phel::bootstrap($argv[2]);
$version = \Phel\Shared\VersionResolver::current();
(new \Phel\Compiler\CompilerFacade())->compileForCache('42', (new \Phel\Shared\CompileOptions())->setEmitOnly(true));
$entries = (new \Phel\Compiler\Infrastructure\Cache\FileSystemReaderResultCache($argv[2] . '/cache', $version))->load('42', 0);
echo json_encode([
    'version' => $version,
    'found' => $entries !== null,
    'value' => $entries === null ? null : $entries[0]->readerResult->getAst(),
    'files' => glob($argv[2] . '/cache/read-result/*.cache'),
], JSON_THROW_ON_ERROR);
PHP;
        $result = Subprocess::run(
            [PHP_BINARY, '-r', $script, dirname(__DIR__, 4), $this->project],
            env: [...getenv(), 'PHEL_CACHE_DIR' => $this->project . '/cache'],
        );
        self::assertSame(0, $result->exitCode, $result->stderr);
        $data = json_decode($result->stdout, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        return $data;
    }
}
