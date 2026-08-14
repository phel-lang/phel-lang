<?php

declare(strict_types=1);

namespace PhelTest\Unit\Api\Application;

use Phel\Api\Application\PhelFileIterator;
use PHPUnit\Framework\TestCase;

use function sprintf;
use function sys_get_temp_dir;
use function uniqid;

/**
 * The LSP indexes a whole workspace root, and a project that has been built
 * has copies of its own `.phel` sources under the build output directory.
 * Indexing those made navigation land in `out/` instead of `src/` or
 * `vendor/` (#3155), so the iterator has to be able to skip a subtree.
 */
final class PhelFileIteratorTest extends TestCase
{
    private string $root = '';

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/phel-file-iterator-' . uniqid();

        foreach (['src', 'out/src', 'vendor/phel-lang/src', 'nested/deep'] as $dir) {
            mkdir($this->root . '/' . $dir, 0777, true);
        }

        foreach ([
            'src/app.phel',
            'out/src/app.phel',
            'vendor/phel-lang/src/core.phel',
            'nested/deep/thing.phel',
            'src/notes.txt',
        ] as $file) {
            file_put_contents($this->root . '/' . $file, '(ns x)');
        }
    }

    protected function tearDown(): void
    {
        // Derived from the property, asserted non-empty and under the system
        // temp directory before anything is removed.
        self::assertNotSame('', $this->root);
        self::assertStringStartsWith(sys_get_temp_dir(), $this->root);
        exec(sprintf('rm -rf %s', escapeshellarg($this->root)));
    }

    public function test_it_yields_every_phel_file_when_nothing_is_excluded(): void
    {
        $files = $this->relativeFiles([]);

        self::assertContains('/src/app.phel', $files);
        self::assertContains('/out/src/app.phel', $files);
        self::assertContains('/vendor/phel-lang/src/core.phel', $files);
        self::assertContains('/nested/deep/thing.phel', $files);
        self::assertNotContains('/src/notes.txt', $files, 'only .phel files');
    }

    public function test_an_excluded_subtree_is_skipped(): void
    {
        $files = $this->relativeFiles([$this->root . '/out/']);

        self::assertNotContains('/out/src/app.phel', $files);
        self::assertContains('/src/app.phel', $files, 'the real source survives');
        self::assertContains(
            '/vendor/phel-lang/src/core.phel',
            $files,
            'vendor is never excluded: navigating into the core library is the point',
        );
    }

    public function test_several_subtrees_can_be_excluded(): void
    {
        $files = $this->relativeFiles([$this->root . '/out/', $this->root . '/nested/']);

        self::assertSame(['/src/app.phel', '/vendor/phel-lang/src/core.phel'], $files);
    }

    public function test_an_excluded_directory_that_does_not_exist_changes_nothing(): void
    {
        self::assertSame(
            $this->relativeFiles([]),
            $this->relativeFiles([$this->root . '/no-such-dir/']),
        );
    }

    public function test_a_prefix_does_not_match_a_sibling_with_the_same_start(): void
    {
        mkdir($this->root . '/output', 0777, true);
        file_put_contents($this->root . '/output/keep.phel', '(ns x)');

        $files = $this->relativeFiles([$this->root . '/out/']);

        self::assertContains('/output/keep.phel', $files, 'out/ must not swallow output/');
        self::assertNotContains('/out/src/app.phel', $files);
    }

    /**
     * @param list<string> $excluded
     *
     * @return list<string>
     */
    private function relativeFiles(array $excluded): array
    {
        $files = [];
        foreach (PhelFileIterator::iterate($this->root, $excluded) as $file) {
            $files[] = str_replace($this->root, '', $file);
        }

        sort($files);

        return array_values($files);
    }
}
