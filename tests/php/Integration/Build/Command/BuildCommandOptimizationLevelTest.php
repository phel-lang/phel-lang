<?php

declare(strict_types=1);

namespace PhelTest\Integration\Build\Command;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use Phel\Build\Infrastructure\Command\BuildCommand;
use PhelTest\Integration\Util\DirectoryUtil;
use PhelTest\Support\PerTestGacelaCache;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\OutputInterface;

use function ob_end_clean;
use function ob_start;

final class BuildCommandOptimizationLevelTest extends TestCase
{
    private BuildCommand $command;

    public static function tearDownAfterClass(): void
    {
        DirectoryUtil::removeDir(__DIR__ . '/out-optimization');
    }

    protected function setUp(): void
    {
        new PerTestGacelaCache()->isolate();
        $this->command = new BuildCommand();
        DirectoryUtil::removeDir(__DIR__ . '/out-optimization');
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_config_level_two_optimizes_build_output(): void
    {
        $this->bootstrapGacela();

        $this->runBuild(['--no-source-map' => true, '--no-cache' => true]);

        $php = $this->compiledOutput();
        // Self-recursive tail call rewritten into an implicit loop.
        self::assertStringContainsString('while (true)', $php);
        // `(add2 1 2)` inlined and folded to the literal 3.
        self::assertMatchesRegularExpression('/"result",\s+3,/', $php);
        self::assertStringNotContainsString('"add2"))->__invoke(1, 2)', $php);

        self::assertFileExists(__DIR__ . '/out-optimization/.phel-optimization-level');
        self::assertSame('2', file_get_contents(__DIR__ . '/out-optimization/.phel-optimization-level'));
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_cli_override_takes_precedence_over_config(): void
    {
        $this->bootstrapGacela();

        $this->runBuild([
            '--no-source-map' => true,
            '--no-cache' => true,
            '--optimization-level' => '0',
        ]);

        $php = $this->compiledOutput();
        self::assertStringNotContainsString('while (true)', $php);
        self::assertStringContainsString('"add2"))->__invoke(1, 2)', $php);

        // Level 0 leaves no marker behind.
        self::assertFileDoesNotExist(__DIR__ . '/out-optimization/.phel-optimization-level');
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_level_change_invalidates_incremental_cache(): void
    {
        $this->bootstrapGacela();

        // First build at level 0 with the incremental cache enabled.
        $this->runBuild(['--no-source-map' => true, '--optimization-level' => '0']);
        self::assertStringNotContainsString('while (true)', $this->compiledOutput());

        // Same sources, cache still enabled: only the level changed (config
        // level 2 applies). The mtime-based cache alone would reuse the stale
        // level-0 output; the on-disk level marker must force a recompile.
        $this->runBuild(['--no-source-map' => true]);
        self::assertStringContainsString('while (true)', $this->compiledOutput());
    }

    /**
     * @param array<string, bool|string> $input
     */
    private function runBuild(array $input): void
    {
        ob_start();
        try {
            $this->command->run(
                new ArrayInput($input),
                $this->createStub(OutputInterface::class),
            );
        } finally {
            ob_end_clean();
        }
    }

    private function compiledOutput(): string
    {
        $file = __DIR__ . '/out-optimization/opt_ns/opt.php';
        self::assertFileExists($file);

        return (string) file_get_contents($file);
    }

    private function bootstrapGacela(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->addAppConfig('phel-config-optimization.php');
        });
    }
}
