<?php

declare(strict_types=1);

namespace PhelTest\Integration\Phar;

use Phar;
use PhelTest\Support\RemoveDirTrait;
use PhelTest\Support\Subprocess;
use PHPUnit\Framework\TestCase;
use RecursiveIteratorIterator;

use function dirname;
use function strlen;

final class PharPackagingTest extends TestCase
{
    use RemoveDirTrait;

    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/phel-packaging-' . bin2hex(random_bytes(6));
        foreach (['build', 'bin', 'src/phel', 'stubs'] as $directory) {
            mkdir($this->root . '/' . $directory, 0755, true);
        }

        foreach (['phar.sh', 'build-phar.php'] as $file) {
            copy(dirname(__DIR__, 4) . '/build/' . $file, $this->root . '/build/' . $file);
        }

        file_put_contents($this->root . '/composer.lock', '{}');
        file_put_contents($this->root . '/runtime.txt', 'tracked original');
        file_put_contents($this->root . '/src/phel/core.phel', '');
        file_put_contents($this->root . '/bin/phel', '<?php');
        file_put_contents($this->root . '/stubs/composer', "#!/usr/bin/env bash\nmkdir -p vendor\nprintf '<?php' > vendor/autoload.php\n");
        chmod($this->root . '/stubs/composer', 0755);
        $this->command(['git', 'init', '--quiet']);
        $this->command(['git', 'add', 'build', 'bin', 'src', 'composer.lock', 'runtime.txt']);
        $this->command(['git', '-c', 'user.name=Packaging test', '-c', 'user.email=packaging@example.test', 'commit', '--quiet', '-m', 'fixture']);
    }

    protected function tearDown(): void
    {
        echo 'Removing packaging fixture: ' . $this->root . "\n";
        $this->removeDir($this->root);
    }

    public function test_untracked_files_are_excluded_without_discarding_tracked_working_edits(): void
    {
        file_put_contents($this->root . '/secret.txt', 'must not ship');
        file_put_contents($this->root . '/runtime.txt', 'tracked working edit');
        $this->build();

        $phar = new Phar($this->root . '/build/out/phel.phar');
        self::assertFalse(isset($phar['secret.txt']));
        self::assertSame('tracked working edit', $phar['runtime.txt']->getContent());
        self::assertTrue(isset($phar['vendor/autoload.php']));
    }

    public function test_archive_entries_use_the_source_epoch(): void
    {
        $this->build();

        $phar = new Phar($this->root . '/build/out/phel.phar');
        foreach (new RecursiveIteratorIterator($phar) as $entry) {
            self::assertSame(1700000000, $entry->getMTime(), $entry->getPathname());
        }
    }

    public function test_repeated_builds_are_byte_identical(): void
    {
        $this->build();
        $firstHash = hash_file('sha256', $this->root . '/build/out/phel.phar');
        sleep(1);
        touch($this->root . '/runtime.txt', 1800000000);
        $this->build();

        self::assertSame($firstHash, hash_file('sha256', $this->root . '/build/out/phel.phar'));
    }

    public function test_equivalent_trees_created_in_reverse_orders_have_identical_canonical_archives(): void
    {
        $compiler = <<<'PHP'
            <?php
            $files = ['core', 'core/z', 'core/a'];
            if (getenv('FIXTURE_REVERSE') === '1') {
                $files = array_reverse($files);
            }
            foreach ($files as $file) {
                $target = dirname(__DIR__) . '/out/phel/' . $file;
                if (!is_dir(dirname($target))) {
                    mkdir(dirname($target), 0755, true);
                }
                file_put_contents($target . '.phel', $file);
                file_put_contents($target . '.php', '<?php /* ' . $file . ' */');
            }
            PHP;
        $files = [
            'vendor/z/data.txt' => 'dependency z',
            'vendor/a/data.txt' => 'dependency a',
            'vendor/autoload.php' => '<?php',
            'bin/phel' => $compiler,
            'composer.lock' => '{}',
            'src/phel/core.phel' => 'core',
            'src/phel/core/z.phel' => 'core/z',
            'src/phel/core/a.phel' => 'core/a',
            'resources/agents/examples/z/main.phel' => 'template z',
            'resources/agents/examples/a/main.phel' => 'template a',
        ];
        $hashes = [];
        foreach ([false, true] as $reverse) {
            $root = $this->root . ($reverse ? '/reverse' : '/forward');
            foreach ($reverse ? array_reverse($files, true) : $files as $path => $content) {
                if (!is_dir(dirname($root . '/' . $path))) {
                    mkdir(dirname($root . '/' . $path), 0755, true);
                }

                file_put_contents($root . '/' . $path, $content);
            }

            $environment = getenv();
            $environment['SOURCE_DATE_EPOCH'] = '1700000000';
            $environment['FIXTURE_REVERSE'] = $reverse ? '1' : '0';
            $process = Subprocess::run([
                PHP_BINARY, '-d', 'phar.readonly=0', $this->root . '/build/build-phar.php', $root,
            ], $root, env: $environment);
            self::assertSame(0, $process->exitCode, $process->stdout . $process->stderr);
            $archive = $root . '/phel.phar';
            $hashes[] = hash_file('sha256', $archive);

            $mainPaths = array_values(array_filter(array_keys($files), static fn(string $path): bool => !str_starts_with($path, 'resources/') && $path !== 'composer.lock'));
            sort($mainPaths, SORT_STRING);
            $expectedPaths = [...$mainPaths, 'src/phel/core.php', 'src/phel/core/a.php', 'src/phel/core/z.php', 'resources/agents/examples/a/main.phel', 'resources/agents/examples/z/main.phel'];
            self::assertSame($expectedPaths, $this->manifestPaths($archive));
        }

        self::assertSame($hashes[0], $hashes[1]);
    }

    public function test_invalid_source_epoch_is_rejected(): void
    {
        $environment = getenv();
        $environment['SOURCE_DATE_EPOCH'] = '4294967296';
        $environment['PATH'] = $this->root . '/stubs:' . $environment['PATH'];
        $process = Subprocess::run(['bash', 'build/phar.sh'], $this->root, env: $environment);

        self::assertNotSame(0, $process->exitCode);
        self::assertStringContainsString('SOURCE_DATE_EPOCH must be an integer between 0 and 4294967295', $process->stderr);
        self::assertFileDoesNotExist($this->root . '/build/out/phel.phar');
    }

    public function test_source_epoch_defaults_to_the_commit_time(): void
    {
        $environment = getenv();
        unset($environment['SOURCE_DATE_EPOCH']);
        $environment['PATH'] = $this->root . '/stubs:' . $environment['PATH'];
        $process = Subprocess::run(['bash', 'build/phar.sh'], $this->root, env: $environment);
        self::assertSame(0, $process->exitCode, $process->stdout . $process->stderr);
        $commit = Subprocess::run(['git', 'log', '-1', '--format=%ct'], $this->root);

        $phar = new Phar($this->root . '/build/out/phel.phar');
        self::assertSame(trim($commit->stdout), (string) $phar['runtime.txt']->getMTime());
    }

    private function build(): void
    {
        $this->command(['bash', 'build/phar.sh']);
    }

    /**
     * @return list<string>
     */
    private function manifestPaths(string $path): array
    {
        $archive = file_get_contents($path);
        $offset = strlen(new Phar($path)->getStub()) + 4;
        $header = unpack('Vfiles/vapi/Vflags/ValiasLength', substr($archive, $offset, 14));
        $offset += 14 + $header['aliasLength'];
        $offset += 4 + unpack('V', substr($archive, $offset, 4))[1];
        $paths = [];
        for ($entry = 0; $entry < $header['files']; ++$entry) {
            $length = unpack('V', substr($archive, $offset, 4))[1];
            $paths[] = substr($archive, $offset + 4, $length);
            $offset += 4 + $length + 20;
            $offset += 4 + unpack('V', substr($archive, $offset, 4))[1];
        }

        return $paths;
    }

    /**
     * @param list<string> $command
     */
    private function command(array $command): void
    {
        $environment = getenv();
        $environment['PATH'] = $this->root . '/stubs:' . $environment['PATH'];
        $environment['PHEL_NO_OPCACHE_REEXEC'] = '1';
        $environment['SOURCE_DATE_EPOCH'] = '1700000000';
        $process = Subprocess::run($command, $this->root, env: $environment);
        self::assertSame(0, $process->exitCode, $process->stdout . $process->stderr);
    }
}
