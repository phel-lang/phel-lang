<?php

declare(strict_types=1);

namespace PhelTest\Integration\Build\Command;

use FilesystemIterator;
use PhelTest\Support\RemoveDirTrait;
use PhelTest\Support\Subprocess;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

use function dirname;
use function strlen;

final class BuildCommandPortablePathsTest extends TestCase
{
    use RemoveDirTrait;

    private string $root;

    private string $repo;

    protected function setUp(): void
    {
        $this->repo = dirname(__DIR__, 5);
        $this->root = sys_get_temp_dir() . '/phel-portable-' . bin2hex(random_bytes(6));
        mkdir($this->root, 0o700, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->root);
    }

    public function test_builds_are_identical_and_run_after_relocation_without_the_source_tree(): void
    {
        $first = $this->createProject('first');
        $second = $this->createProject('a-much-longer-second-build-directory');
        $this->build($first);
        $this->build($second);
        self::assertSame($this->artifactContents($first), $this->artifactContents($second));

        $relocated = $this->root . '/relocated';
        rename($first, $relocated);
        rename($relocated . '/src', $relocated . '/original-src');
        $result = $this->runArtifact($relocated);

        self::assertSame(0, $result->exitCode, $result->stderr);
        $values = json_decode($result->stdout, true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(42, $values['child']);
        self::assertSame(realpath($relocated . '/out/portable/main.phel'), $values['file']);
        self::assertSame(realpath($relocated . '/out/portable'), $values['directory']);
        self::assertSame(realpath($relocated . '/out/portable/main.php'), $values['php-file']);
        self::assertSame(realpath($relocated . '/out/portable'), $values['php-directory']);
        self::assertSame($values['file'], $values['metadata-file']);
        self::assertSame($values['directory'] . '/sibling', $values['combined']);

        $error = $this->runArtifact($relocated, true);
        self::assertSame(255, $error->exitCode, $error->stdout . $error->stderr);
        self::assertStringContainsString('main.phel:9', $error->stderr);
        self::assertStringContainsString('portable boom', $error->stderr);
        self::assertStringNotContainsString($first, $error->stderr);
    }

    public function test_warm_build_macros_keep_original_source_paths_and_incremental_stamps(): void
    {
        $project = $this->createProject('warm');
        file_put_contents($project . '/src/macros.phel', <<<'PHEL'
            (ns portable.macros)
            (defmacro answer [] (php/file_get_contents (str __DIR__ "/answer.txt")))
            PHEL);
        file_put_contents($project . '/src/answer.txt', '42');
        file_put_contents($project . '/src/main.phel', "(ns portable.main (:require portable.macros :as m))\n(def answer (m/answer))\n");
        touch($project . '/src/macros.phel', 1);
        touch($project . '/src/main.phel', 1);
        $this->build($project);
        $macroTarget = $project . '/out/portable/macros.php';
        self::assertSame(1, filemtime($macroTarget));

        file_put_contents($project . '/src/main.phel', "(ns portable.main (:require portable.macros :as m))\n(def answer (m/answer))\n(def changed true)\n");
        $this->build($project, false);
        clearstatcache();
        self::assertSame(1, filemtime($macroTarget));
    }

    public function test_an_identical_user_string_stays_data_when_magic_paths_relocate(): void
    {
        $project = $this->createProject('literal');
        $sourceFile = realpath($project . '/src/main.phel');
        self::assertIsString($sourceFile);
        $source = (string) file_get_contents($sourceFile);
        $literal = json_encode($sourceFile, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        file_put_contents($sourceFile, str_replace('"ordinary data"', $literal, $source));
        $this->build($project);
        $relocated = $this->root . '/relocated-literal';
        rename($project, $relocated);

        $result = $this->runArtifact($relocated);

        self::assertSame(0, $result->exitCode, $result->stderr);
        $values = json_decode($result->stdout, true, flags: JSON_THROW_ON_ERROR);
        self::assertSame($sourceFile, $values['literal']);
        self::assertSame(realpath($relocated . '/out/portable/main.phel'), $values['file']);
    }

    public function test_a_relocated_library_error_still_points_at_the_user_call(): void
    {
        $project = $this->createProject('library-error');
        $sourceFile = $project . '/src/main.phel';
        $source = (string) file_get_contents($sourceFile);
        file_put_contents($sourceFile, str_replace('(throw (new \\RuntimeException "portable boom"))', '(nth [] (php/random_int 1 1))', $source));
        $this->build($project);
        $relocated = $this->root . '/relocated-error';
        rename($project, $relocated);
        rename($relocated . '/src', $relocated . '/original-src');

        $error = $this->runArtifact($relocated, true);

        self::assertSame(255, $error->exitCode, $error->stdout . $error->stderr);
        self::assertStringContainsString('main.phel:9', $error->stderr);
        self::assertStringNotContainsString($project, $error->stderr);
    }

    public function test_a_newline_in_the_build_directory_keeps_relocated_source_maps(): void
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            self::markTestSkipped('Windows does not support line feeds in directory names.');
        }

        $project = $this->createProject("line\nbreak");
        $second = $this->createProject('ordinary-longer-build-directory');
        $this->build($project);
        $this->build($second);
        self::assertSame($this->artifactContents($second), $this->artifactContents($project));
        $relocated = $this->root . '/relocated-newline';
        rename($project, $relocated);
        rename($relocated . '/src', $relocated . '/original-src');

        $error = $this->runArtifact($relocated, true);

        self::assertSame(255, $error->exitCode, $error->stdout . $error->stderr);
        self::assertStringContainsString('main.phel:9', $error->stderr);
    }

    private function createProject(string $name): string
    {
        $project = $this->root . '/' . $name;
        mkdir($project . '/src', 0o700, true);
        mkdir($project . '/vendor');
        file_put_contents($project . '/vendor/autoload.php', '<?php return require ' . var_export($this->repo . '/vendor/autoload.php', true) . ';');
        file_put_contents($project . '/phel-config.php', <<<'PHP'
            <?php return new \Phel\Config\PhelConfig()
                ->withSrcDirs(['src'])->withVendorDir('')
                ->withMainPhelNamespace('portable.main')->withMainPhpPath('out/entry.php');
            PHP);
        file_put_contents($project . '/src/main.phel', <<<'PHEL'
            (ns portable.main)
            (load "child")
            (def source-file __FILE__)
            (def source-directory __DIR__)
            (def php-source-file php/__FILE__)
            (def php-source-directory php/__DIR__)
            (def literal "ordinary data")
            (def combined-path (str __DIR__ "/sibling"))
            (defn explode-now [] (throw (new \RuntimeException "portable boom")))
            PHEL);
        file_put_contents($project . '/src/child.phel', "(in-ns portable.main)\n(load \"nested\")\n(def child-value (nested-value))\n");
        file_put_contents($project . '/src/nested.phel', "(in-ns portable.main)\n(defn nested-value [] 42)\n");

        return $project;
    }

    private function build(string $project, bool $fresh = true): void
    {
        $args = [PHP_BINARY, $this->repo . '/bin/phel', 'build', '-O2'];
        if ($fresh) {
            $args[] = '--no-cache';
        }

        $result = Subprocess::run($args, $project, env: [...getenv(), 'PHEL_CACHE_DIR' => $project . '/cache']);
        self::assertSame(0, $result->exitCode, $result->stdout . $result->stderr);
    }

    /**
     * @return array<string, string>
     */
    private function artifactContents(string $project): array
    {
        $contents = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($project . '/out', FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->isFile()) {
                $relative = substr($file->getPathname(), strlen($project . '/out/'));
                $contents[str_replace('\\', '/', $relative)] = (string) file_get_contents($file->getPathname());
            }
        }

        ksort($contents);

        return $contents;
    }

    private function runArtifact(string $project, bool $error = false): Subprocess
    {
        file_put_contents($project . '/run-artifact.php', <<<'PHP'
            <?php
            require __DIR__ . '/vendor/autoload.php';
            \Phel\Phel::installExceptionHandler(__DIR__);
            require __DIR__ . '/out/entry.php';
            if (($argv[1] ?? '') === 'error') {
                \Phel::getDefinition('portable.main', 'explode-now')();
            }
            $metadata = \Phel::getDefinitionMetaData('portable.main', 'explode-now');
            $location = $metadata->find(\Phel::keyword('start-location'));
            echo json_encode([
                'child' => \Phel::getDefinition('portable.main', 'child-value'),
                'file' => \Phel::getDefinition('portable.main', 'source-file'),
                'directory' => \Phel::getDefinition('portable.main', 'source-directory'),
                'php-file' => \Phel::getDefinition('portable.main', 'php-source-file'),
                'php-directory' => \Phel::getDefinition('portable.main', 'php-source-directory'),
                'metadata-file' => $location->find(\Phel::keyword('file')),
                'combined' => \Phel::getDefinition('portable.main', 'combined-path'),
                'literal' => \Phel::getDefinition('portable.main', 'literal'),
            ]);
            PHP);

        return Subprocess::run([
            PHP_BINARY, '-d', 'display_errors=1', '-d', 'log_errors=0',
            $project . '/run-artifact.php', $error ? 'error' : 'success',
        ], $project);
    }
}
