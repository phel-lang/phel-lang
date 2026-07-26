<?php

declare(strict_types=1);

namespace PhelTest\Unit\Run\Application\Agent;

use Phel\Run\Application\Agent\AgentInstaller;
use Phel\Run\Domain\Agent\AgentPlatform;
use PhelTest\Support\RemoveDirTrait;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function dirname;

final class AgentInstallerTest extends TestCase
{
    use RemoveDirTrait;

    private AgentInstaller $installer;

    private string $sourceRoot;

    private string $projectRoot;

    private AgentPlatform $platform;

    protected function setUp(): void
    {
        $this->installer = new AgentInstaller();
        $this->sourceRoot = $this->makeDir('source');
        $this->projectRoot = $this->makeDir('project');
        $this->platform = new AgentPlatform('test', 'skills/test/SKILL.md', '.test/SKILL.md', ['.test']);

        $this->writeFile($this->sourceRoot . '/VERSION', "9.9.9\n");
        $this->writeFile($this->sourceRoot . '/skills/test/SKILL.md', 'skill v2');
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->sourceRoot);
        $this->removeDir($this->projectRoot);
    }

    public function test_install_skill_copies_file_without_backup_when_target_absent(): void
    {
        $backedUp = $this->installer->installSkill($this->sourceRoot, $this->projectRoot, $this->platform, false);

        self::assertFalse($backedUp);
        self::assertSame('skill v2', file_get_contents($this->projectRoot . '/.test/SKILL.md'));
        self::assertFileDoesNotExist($this->projectRoot . '/.test/SKILL.md.pre-phel.bak');
    }

    public function test_install_skill_backs_up_existing_target(): void
    {
        $this->writeFile($this->projectRoot . '/.test/SKILL.md', 'user v1');

        $backedUp = $this->installer->installSkill($this->sourceRoot, $this->projectRoot, $this->platform, false);

        self::assertTrue($backedUp);
        self::assertSame('user v1', file_get_contents($this->projectRoot . '/.test/SKILL.md.pre-phel.bak'));
        self::assertSame('skill v2', file_get_contents($this->projectRoot . '/.test/SKILL.md'));
    }

    public function test_install_skill_force_overwrites_without_backup(): void
    {
        $this->writeFile($this->projectRoot . '/.test/SKILL.md', 'user v1');

        $backedUp = $this->installer->installSkill($this->sourceRoot, $this->projectRoot, $this->platform, true);

        self::assertFalse($backedUp);
        self::assertFileDoesNotExist($this->projectRoot . '/.test/SKILL.md.pre-phel.bak');
        self::assertSame('skill v2', file_get_contents($this->projectRoot . '/.test/SKILL.md'));
    }

    public function test_install_skill_throws_when_source_missing(): void
    {
        $missing = new AgentPlatform('nope', 'skills/nope/SKILL.md', '.nope/SKILL.md', []);

        $this->expectException(RuntimeException::class);
        $this->installer->installSkill($this->sourceRoot, $this->projectRoot, $missing, false);
    }

    public function test_uninstall_skill_removes_when_no_backup(): void
    {
        $this->writeFile($this->projectRoot . '/.test/SKILL.md', 'x');

        $result = $this->installer->uninstallSkill($this->projectRoot, $this->platform);

        self::assertSame(AgentInstaller::UNINSTALL_REMOVED, $result);
        self::assertFileDoesNotExist($this->projectRoot . '/.test/SKILL.md');
    }

    public function test_uninstall_skill_restores_backup(): void
    {
        $this->writeFile($this->projectRoot . '/.test/SKILL.md', 'installed');
        $this->writeFile($this->projectRoot . '/.test/SKILL.md.pre-phel.bak', 'user original');

        $result = $this->installer->uninstallSkill($this->projectRoot, $this->platform);

        self::assertSame(AgentInstaller::UNINSTALL_RESTORED, $result);
        self::assertSame('user original', file_get_contents($this->projectRoot . '/.test/SKILL.md'));
        self::assertFileDoesNotExist($this->projectRoot . '/.test/SKILL.md.pre-phel.bak');
    }

    public function test_uninstall_skill_reports_absent_when_not_installed(): void
    {
        self::assertSame(
            AgentInstaller::UNINSTALL_ABSENT,
            $this->installer->uninstallSkill($this->projectRoot, $this->platform),
        );
    }

    public function test_bundled_docs_version_reads_the_version_marker(): void
    {
        self::assertSame('9.9.9', $this->installer->bundledDocsVersion($this->sourceRoot));
    }

    public function test_bundled_docs_version_is_empty_when_the_marker_is_missing(): void
    {
        self::assertSame('', $this->installer->bundledDocsVersion($this->projectRoot));
    }

    public function test_locate_source_root_points_at_bundled_resources(): void
    {
        $root = $this->installer->locateSourceRoot();

        self::assertDirectoryExists($root);
        self::assertStringEndsWith('/resources/agents', $root);
    }

    private function makeDir(string $suffix): string
    {
        $dir = sys_get_temp_dir() . '/phel-installer-test-' . uniqid() . '-' . $suffix;
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
