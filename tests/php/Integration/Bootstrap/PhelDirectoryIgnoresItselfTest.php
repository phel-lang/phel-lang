<?php

declare(strict_types=1);

namespace PhelTest\Integration\Bootstrap;

use PhelTest\Support\RemoveDirTrait;
use PhelTest\Support\Subprocess;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function dirname;
use function file_get_contents;
use function file_put_contents;
use function getenv;
use function mkdir;
use function realpath;
use function sys_get_temp_dir;
use function uniqid;

use const PHP_BINARY;

/**
 * Every command writes into `.phel/`, so every command must leave the
 * `.phel/.gitignore` that keeps it out of `git status` (#3537).
 */
final class PhelDirectoryIgnoresItselfTest extends TestCase
{
    use RemoveDirTrait;

    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = realpath(sys_get_temp_dir()) . '/phel-dir-ignore-' . uniqid();
        mkdir($this->projectDir . '/src', 0777, true);
        file_put_contents(
            $this->projectDir . '/phel-config.php',
            "<?php\n\nreturn (new \\Phel\\Config\\PhelConfig())->withSrcDirs(['src'])->withMainPhelNamespace('app\\\\main');\n",
        );
        file_put_contents(
            $this->projectDir . '/src/main.phel',
            "(ns app\\main)\n\n(println \"hi\")\n",
        );
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->projectDir);
    }

    /**
     * @param list<string> $args
     */
    #[DataProvider('provideCommands')]
    public function test_command_seeds_the_gitignore_of_the_phel_directory(array $args): void
    {
        $process = Subprocess::run(
            [PHP_BINARY, dirname(__DIR__, 4) . '/bin/phel', ...$args],
            $this->projectDir,
            env: [
                'PHEL_NO_OPCACHE_REEXEC' => '1',
                'PATH' => (string) getenv('PATH'),
                'HOME' => (string) getenv('HOME'),
                'TMPDIR' => (string) (getenv('TMPDIR') ?: '/tmp'),
            ],
        );

        self::assertSame(0, $process->exitCode, $process->stdout . $process->stderr);
        self::assertDirectoryExists($this->projectDir . '/.phel', '.phel/ was not created');
        self::assertFileExists($this->projectDir . '/.phel/.gitignore');
        self::assertStringContainsString("\n*\n", (string) file_get_contents($this->projectDir . '/.phel/.gitignore'));
    }

    /**
     * @return iterable<string, array{list<string>}>
     */
    public static function provideCommands(): iterable
    {
        yield 'run' => [['run', 'src/main.phel']];
        yield 'build' => [['build', '--no-cache']];
        yield 'eval' => [['eval', '(+ 1 2)']];
    }
}
