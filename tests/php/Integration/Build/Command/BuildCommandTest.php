<?php

declare(strict_types=1);

namespace PhelTest\Integration\Build\Command;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use Phel\Build\Infrastructure\Command\BuildCommand;
use Phel\Phel;
use PhelTest\Support\PerTestGacelaCache;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

use function ob_get_clean;
use function ob_start;

final class BuildCommandTest extends TestCase
{
    private BuildCommandWorkspace $workspace;

    private BuildCommand $command;

    protected function setUp(): void
    {
        // These tests run in a separate process, where the PHPUnit extension
        // that isolates the Gacela cache does not apply; isolate it here so the
        // shared on-disk cache cannot leak between sibling process-isolated tests.
        new PerTestGacelaCache()->isolate();
        $this->command = new BuildCommand();
        $this->workspace = new BuildCommandWorkspace('main');
        $this->workspace
            ->import('phel-config.php')
            ->import('phel-config-failing.php')
            ->import('src')
            ->import('src-failing');
    }

    protected function tearDown(): void
    {
        $this->workspace->remove();
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_build_project(): void
    {
        $this->bootstrapGacela();

        ob_start();
        $this->command->run(
            new ArrayInput([
                '--no-source-map' => true,
                '--no-cache' => true,
            ]),
            $this->createStub(OutputInterface::class),
        );
        $string = ob_get_clean();

        // Top-level side-effects from compiled Phel code must not leak to stdout.
        self::assertDoesNotMatchRegularExpression('/This is printed from no-cache.phel/', $string);
        self::assertDoesNotMatchRegularExpression('/This is printed from hello.phel/', $string);

        self::assertFileExists($this->workspace->path('out/phel/core.phel'));
        self::assertFileExists($this->workspace->path('out/phel/core.php'));
        self::assertFileExists($this->workspace->path('out/test_ns/hello.phel'));
        self::assertFileExists($this->workspace->path('out/test_ns/hello.php'));
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_build_project_cached(): void
    {
        $this->bootstrapGacela();

        // First build populates the incremental cache.
        ob_start();
        $this->command->run(
            new ArrayInput(['--no-source-map' => true, '--no-cache' => true]),
            $this->createStub(OutputInterface::class),
        );
        ob_get_clean();

        $targetFile = $this->workspace->path('out/test_ns/hello.php');
        // Mark file cache invalid by setting the modification time to 0.
        touch($targetFile, 1);

        ob_start();
        $this->command->run(
            new ArrayInput([
                '--no-source-map' => true,
            ]),
            $this->createStub(OutputInterface::class),
        );
        $string = ob_get_clean();

        // Compiled program output must stay suppressed regardless of cache path (fresh or cached require_once).
        self::assertDoesNotMatchRegularExpression('/This is printed from no-cache.phel/', $string);
        self::assertDoesNotMatchRegularExpression('/This is printed from hello.phel/', $string);

        self::assertFileExists($this->workspace->path('out/phel/core.phel'));
        self::assertFileExists($this->workspace->path('out/phel/core.php'));
        self::assertFileExists($this->workspace->path('out/test_ns/hello.phel'));
        self::assertFileExists($targetFile);
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_out_main_file(): void
    {
        $this->bootstrapGacela();

        ob_start();
        $this->command->run(
            new ArrayInput([
                '--no-source-map' => true,
                '--no-cache' => true,
            ]),
            $this->createStub(OutputInterface::class),
        );
        ob_end_clean();

        $actual = file_get_contents($this->workspace->path('out/main.php'));
        $expected = <<<'TXT'
<?php declare(strict_types=1);

require_once dirname(__DIR__) . "/vendor/autoload.php";

// Normalize argv: program is $argv[0], user args are the rest
\Phel\Phel::setupRuntimeArgs($argv[0] ?? __FILE__, array_slice($argv ?? [], 1));

$compiledFile = __DIR__ . "/test_ns/hello.php";

require_once $compiledFile;
TXT;
        self::assertSame($expected, $actual);
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_no_entrypoint_when_namespace_is_not_set(): void
    {
        $this->workspace->writeFile('phel-config-no-namespace.php', <<<'PHP'
            <?php

            declare(strict_types=1);

            use Phel\Config\PhelConfig;

            return new PhelConfig()
                ->withSrcDirs([__DIR__ . '/src'])
                ->withVendorDir('')
                ->withBuildDestDir('out')
                ->withIgnoreWhenBuilding(['local.phel', 'failing.phel']);
            PHP);

        Gacela::bootstrap($this->workspace->root(), static function (GacelaConfig $config): void {
            $config->addAppConfig('phel-config-no-namespace.php');
        });

        if (file_exists($this->workspace->path('out/main.php'))) {
            unlink($this->workspace->path('out/main.php'));
        }

        ob_start();
        $this->command->run(
            new ArrayInput([
                '--no-source-map' => true,
                '--no-cache' => true,
            ]),
            $this->createStub(OutputInterface::class),
        );
        ob_end_clean();

        $this->assertFileDoesNotExist($this->workspace->path('out/main.php'));
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_build_exits_nonzero_when_compilation_fails(): void
    {
        Gacela::bootstrap($this->workspace->root(), static function (GacelaConfig $config): void {
            $config->addAppConfig('phel-config-failing.php');
        });

        ob_start();
        $exitCode = $this->command->run(
            new ArrayInput([
                '--no-source-map' => true,
                '--no-cache' => true,
            ]),
            $this->createStub(OutputInterface::class),
        );
        ob_end_clean();

        // A build that aborts on a compiler error must not exit 0: it leaves a
        // partial/empty output tree, and CI relies on the non-zero exit.
        self::assertSame(Command::FAILURE, $exitCode);
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_build_report_prints_namespaces_sizes_and_timing(): void
    {
        $this->bootstrapGacela();

        $output = new BufferedOutput();
        $this->command->run(
            new ArrayInput([
                '--no-source-map' => true,
                '--no-cache' => true,
                '--report' => true,
            ]),
            $output,
        );
        $text = $output->fetch();

        self::assertStringContainsString('Build report', $text);
        self::assertMatchesRegularExpression('/Namespaces: \d+ \(\d+ fresh, \d+ cached\)/', $text);
        self::assertMatchesRegularExpression('/Total: [\d.]+ (B|KB|MB)/', $text);
        self::assertMatchesRegularExpression('/Time: [\d.]+ ms/', $text);
        self::assertStringContainsString('(fresh)', $text);
        self::assertStringContainsString('phel.core', $text);
        self::assertStringContainsString('test-ns.hello', $text);
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_timing_prints_per_phase_compile_breakdown(): void
    {
        $this->bootstrapGacela();

        $output = new BufferedOutput();
        $this->command->run(
            new ArrayInput([
                '--no-source-map' => true,
                '--no-cache' => true,
                '--timing' => true,
            ]),
            $output,
        );
        $text = $output->fetch();

        self::assertStringContainsString('Compile-phase timing', $text);
        self::assertMatchesRegularExpression('/lex\s+[\d.]+ ms\s+[\d.]+%/', $text);
        self::assertMatchesRegularExpression('/analyze\s+[\d.]+ ms\s+[\d.]+%/', $text);
        self::assertMatchesRegularExpression('/total\s+[\d.]+ ms/', $text);
        self::assertMatchesRegularExpression('/\(\d+ namespaces? compiled\)/', $text);
    }

    private function bootstrapGacela(): void
    {
        Phel::bootstrap($this->workspace->root());
    }
}
