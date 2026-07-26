<?php

declare(strict_types=1);

namespace PhelTest\Unit\Run\Application\Agent;

use Phel\Run\Application\Agent\AgentInstaller;
use Phel\Run\Application\Agent\AgentManifestStore;
use Phel\Run\Domain\Agent\AgentDocsSyncResult;
use PhelTest\Support\RemoveDirTrait;
use PHPUnit\Framework\TestCase;

use function dirname;
use function sprintf;

/**
 * The incremental `.agents/` sync: what it writes, what it leaves alone, and
 * what it refuses to touch because the user changed it after we installed it.
 */
final class AgentDocsSyncTest extends TestCase
{
    use RemoveDirTrait;

    private AgentInstaller $installer;

    private string $sourceRoot;

    private string $projectRoot;

    protected function setUp(): void
    {
        $this->installer = new AgentInstaller();
        $this->sourceRoot = $this->makeDir('source');
        $this->projectRoot = $this->makeDir('project');

        $this->writeFile($this->sourceRoot . '/VERSION', "1.0.0\n");
        $this->writeFile($this->sourceRoot . '/RULES.md', 'rules v1');
        $this->writeFile($this->sourceRoot . '/tasks/async.md', 'async v1');
        $this->writeFile($this->sourceRoot . '/examples/app.md', 'example v1');
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->sourceRoot);
        $this->removeDir($this->projectRoot);
    }

    public function test_sync_creates_every_file_on_a_clean_project(): void
    {
        $result = $this->sync();

        self::assertSame(['RULES.md', 'VERSION', 'tasks/async.md'], $result->created);
        self::assertSame([], $result->updated);
        self::assertSame([], $result->unchanged);
        self::assertSame('rules v1', $this->read('.agents/RULES.md'));
        self::assertSame('async v1', $this->read('.agents/tasks/async.md'));
    }

    public function test_sync_excludes_examples_unless_requested(): void
    {
        $this->sync();
        self::assertFileDoesNotExist($this->projectRoot . '/.agents/examples/app.md');

        $result = $this->sync(withExamples: true);

        self::assertSame(['examples/app.md'], $result->created);
    }

    public function test_rerunning_an_unchanged_tree_writes_nothing(): void
    {
        $this->sync();
        $before = $this->mtimes();

        $result = $this->sync();

        self::assertSame([], $result->created);
        self::assertSame([], $result->updated);
        self::assertSame(['RULES.md', 'VERSION', 'tasks/async.md'], $result->unchanged);
        self::assertSame($before, $this->mtimes(), 'untouched files must keep their mtime');
    }

    public function test_sync_updates_only_the_file_whose_source_moved_on(): void
    {
        $this->sync();
        $this->writeFile($this->sourceRoot . '/RULES.md', 'rules v2');

        $result = $this->sync();

        self::assertSame(['RULES.md'], $result->updated);
        self::assertSame(['VERSION', 'tasks/async.md'], $result->unchanged);
        self::assertSame([], $result->created);
        self::assertSame('rules v2', $this->read('.agents/RULES.md'));
    }

    public function test_sync_adds_a_file_that_appeared_in_the_source_tree(): void
    {
        $this->sync();
        $this->writeFile($this->sourceRoot . '/tasks/new-recipe.md', 'brand new');

        $result = $this->sync();

        self::assertSame(['tasks/new-recipe.md'], $result->created);
        self::assertSame('brand new', $this->read('.agents/tasks/new-recipe.md'));
    }

    public function test_sync_leaves_a_locally_modified_file_alone(): void
    {
        $this->sync();
        $this->writeFile($this->projectRoot . '/.agents/RULES.md', 'my own notes');
        $this->writeFile($this->sourceRoot . '/RULES.md', 'rules v2');

        $result = $this->sync();

        self::assertSame(['RULES.md'], $result->skipped);
        self::assertSame([], $result->updated);
        self::assertSame('my own notes', $this->read('.agents/RULES.md'));
    }

    public function test_a_locally_modified_file_stays_skipped_on_every_later_run(): void
    {
        $this->sync();
        $this->writeFile($this->projectRoot . '/.agents/RULES.md', 'my own notes');
        $this->writeFile($this->sourceRoot . '/RULES.md', 'rules v2');
        $this->sync();

        $result = $this->sync();

        self::assertSame(['RULES.md'], $result->skipped);
        self::assertSame('my own notes', $this->read('.agents/RULES.md'));
    }

    public function test_force_backs_the_local_edit_up_before_overwriting_it(): void
    {
        $this->sync();
        $this->writeFile($this->projectRoot . '/.agents/RULES.md', 'my own notes');
        $this->writeFile($this->sourceRoot . '/RULES.md', 'rules v2');

        $result = $this->sync(force: true);

        self::assertSame(['RULES.md'], $result->backedUp);
        self::assertSame([], $result->skipped);
        self::assertSame('rules v2', $this->read('.agents/RULES.md'));
        self::assertSame('my own notes', $this->read('.agents/RULES.md' . AgentInstaller::BACKUP_SUFFIX));
    }

    public function test_a_pre_manifest_tree_is_treated_as_locally_modified(): void
    {
        // What an install from before the manifest existed looks like: files on
        // disk, nothing recording what we shipped.
        $this->writeFile($this->projectRoot . '/.agents/RULES.md', 'ancient copy');

        $result = $this->sync();

        self::assertSame(['RULES.md'], $result->skipped);
        self::assertSame(['VERSION', 'tasks/async.md'], $result->created);
        self::assertSame('ancient copy', $this->read('.agents/RULES.md'));
    }

    public function test_dry_run_reports_the_same_plan_but_writes_nothing(): void
    {
        $this->sync();
        $this->writeFile($this->sourceRoot . '/RULES.md', 'rules v2');
        $this->writeFile($this->sourceRoot . '/tasks/extra.md', 'extra');

        $result = $this->sync(dryRun: true);

        self::assertSame(['tasks/extra.md'], $result->created);
        self::assertSame(['RULES.md'], $result->updated);
        self::assertSame('rules v1', $this->read('.agents/RULES.md'));
        self::assertFileDoesNotExist($this->projectRoot . '/.agents/tasks/extra.md');
    }

    public function test_sync_records_the_bundled_version(): void
    {
        self::assertNull($this->installer->installedDocsVersion($this->projectRoot));

        $this->sync();

        self::assertSame('1.0.0', $this->installer->installedDocsVersion($this->projectRoot));
    }

    public function test_a_dry_run_does_not_record_a_version(): void
    {
        $this->sync(dryRun: true);

        self::assertNull($this->installer->installedDocsVersion($this->projectRoot));
        self::assertDirectoryDoesNotExist($this->projectRoot . '/.agents');
    }

    public function test_remove_docs_keeps_files_the_user_added(): void
    {
        $this->sync();
        $this->writeFile($this->projectRoot . '/.agents/my-notes.md', 'mine');
        $this->writeFile($this->projectRoot . '/.agents/tasks/my-task.md', 'mine too');

        self::assertTrue($this->installer->removeDocs($this->projectRoot));

        self::assertFileDoesNotExist($this->projectRoot . '/.agents/RULES.md');
        self::assertFileDoesNotExist($this->projectRoot . '/.agents/tasks/async.md');
        self::assertSame('mine', $this->read('.agents/my-notes.md'));
        self::assertSame('mine too', $this->read('.agents/tasks/my-task.md'));
    }

    public function test_remove_docs_drops_the_whole_tree_when_nothing_of_the_user_is_left(): void
    {
        $this->sync();

        self::assertTrue($this->installer->removeDocs($this->projectRoot));

        self::assertDirectoryDoesNotExist($this->projectRoot . '/.agents');
    }

    public function test_remove_docs_removes_the_manifest_itself(): void
    {
        $this->sync();
        $this->writeFile($this->projectRoot . '/.agents/my-notes.md', 'mine');

        $this->installer->removeDocs($this->projectRoot);

        self::assertFileDoesNotExist(
            $this->projectRoot . '/.agents/' . AgentManifestStore::FILENAME,
        );
    }

    public function test_remove_docs_falls_back_to_the_whole_tree_without_a_manifest(): void
    {
        $this->writeFile($this->projectRoot . '/.agents/legacy.md', 'from an old install');

        self::assertTrue($this->installer->removeDocs($this->projectRoot));

        self::assertDirectoryDoesNotExist($this->projectRoot . '/.agents');
    }

    public function test_remove_docs_is_a_noop_when_absent(): void
    {
        self::assertFalse($this->installer->removeDocs($this->projectRoot));
    }

    public function test_the_manifest_is_never_offered_back_as_a_synced_file(): void
    {
        $this->sync();

        $result = $this->sync();

        self::assertNotContains(AgentManifestStore::FILENAME, $result->unchanged);
        self::assertNotContains(AgentManifestStore::FILENAME, $result->skipped);
    }

    private function sync(
        bool $force = false,
        bool $withExamples = false,
        bool $dryRun = false,
    ): AgentDocsSyncResult {
        return $this->installer->syncDocs(
            $this->sourceRoot,
            $this->projectRoot,
            $force,
            $withExamples,
            $dryRun,
        );
    }

    private function read(string $relative): string
    {
        return (string) file_get_contents($this->projectRoot . '/' . $relative);
    }

    /**
     * @return array<string, int>
     */
    private function mtimes(): array
    {
        $out = [];
        foreach (['RULES.md', 'tasks/async.md'] as $rel) {
            $path = $this->projectRoot . '/.agents/' . $rel;
            $out[$rel] = (int) filemtime($path);
        }

        return $out;
    }

    private function makeDir(string $suffix): string
    {
        $dir = sprintf('%s/phel-docs-sync-%s-%s', sys_get_temp_dir(), uniqid(), $suffix);
        mkdir($dir, 0o755, true);
        return $dir;
    }

    private function writeFile(string $path, string $contents): void
    {
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0o755, true);
        }

        file_put_contents($path, $contents);
    }

}
