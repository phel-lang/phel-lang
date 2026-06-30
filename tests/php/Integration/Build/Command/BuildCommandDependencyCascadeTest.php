<?php

declare(strict_types=1);

namespace PhelTest\Integration\Build\Command;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use Phel\Build\Infrastructure\Command\BuildCommand;
use PhelTest\Support\PerTestGacelaCache;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\OutputInterface;

use function file_get_contents;
use function file_put_contents;
use function ob_end_clean;
use function ob_start;
use function time;
use function touch;

final class BuildCommandDependencyCascadeTest extends TestCase
{
    private BuildCommandWorkspace $workspace;

    private BuildCommand $command;

    protected function setUp(): void
    {
        new PerTestGacelaCache()->isolate();
        $this->command = new BuildCommand();
        $this->workspace = new BuildCommandWorkspace('cascade');
        $this->workspace
            ->import('phel-config-cascade.php')
            ->import('src-cascade');
    }

    protected function tearDown(): void
    {
        $this->workspace->remove();
    }

    /**
     * A dependent namespace whose own source is unchanged must still be
     * recompiled when a namespace it requires changed — otherwise it keeps a
     * stale macro expansion baked in by the previous build. The incremental
     * cache is mtime-only, so the dependency cascade is what forces this.
     */
    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_changed_dependency_recompiles_unchanged_dependent(): void
    {
        $baseSource = $this->workspace->path('src-cascade/cascade/base.phel');
        $dependentSource = $this->workspace->path('src-cascade/cascade/dependent.phel');

        $this->bootstrapGacela();

        // First build: the macro expands to VERSION_ONE inside the dependent.
        $this->runBuild(['--no-source-map' => true]);
        self::assertStringContainsString('VERSION_ONE', $this->dependentOutput());

        $dependentMtime = filemtime($dependentSource);

        // Change ONLY the base macro and bump its mtime so its own cache entry
        // invalidates. The dependent source stays byte-identical and we restore
        // its mtime, so a mtime-only cache would wrongly reuse the stale output.
        file_put_contents(
            $baseSource,
            "(ns cascade.base)\n\n(defmacro greeting [] \"VERSION_TWO\")\n",
        );
        touch($baseSource, time() + 10);
        touch($dependentSource, (int) $dependentMtime);

        // Second build with the cache still enabled.
        $this->runBuild(['--no-source-map' => true]);

        $output = $this->dependentOutput();
        self::assertStringContainsString('VERSION_TWO', $output);
        self::assertStringNotContainsString('VERSION_ONE', $output);
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

    private function dependentOutput(): string
    {
        $target = $this->workspace->path('out-cascade/cascade/dependent.php');
        self::assertFileExists($target);

        return (string) file_get_contents($target);
    }

    private function bootstrapGacela(): void
    {
        Gacela::bootstrap($this->workspace->root(), static function (GacelaConfig $config): void {
            $config->addAppConfig('phel-config-cascade.php');
        });
    }
}
