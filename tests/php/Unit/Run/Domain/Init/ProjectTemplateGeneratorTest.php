<?php

declare(strict_types=1);

namespace PhelTest\Unit\Run\Domain\Init;

use Phel\Config\ProjectLayout;
use Phel\Run\Domain\Init\ProjectTemplateGenerator;
use PHPUnit\Framework\TestCase;

final class ProjectTemplateGeneratorTest extends TestCase
{
    private ProjectTemplateGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new ProjectTemplateGenerator();
    }

    public function test_generate_config_conventional_layout(): void
    {
        $config = $this->generator->generateConfig('myapp\\core', ProjectLayout::Conventional);

        self::assertStringContainsString('PhelConfig::forProject()', $config);
        self::assertStringNotContainsString('useFlatLayout', $config);
        self::assertStringNotContainsString('ProjectLayout::', $config);
    }

    public function test_generate_config_flat_layout(): void
    {
        $config = $this->generator->generateConfig('myapp\\core', ProjectLayout::Flat);

        self::assertStringContainsString('PhelConfig::forProject(layout:', $config);
        self::assertStringContainsString('ProjectLayout::Flat', $config);
    }

    public function test_generate_config_root_layout(): void
    {
        $config = $this->generator->generateConfig('sandbox\\main', ProjectLayout::Root);

        self::assertStringContainsString('PhelConfig::forProject(layout:', $config);
        self::assertStringContainsString('ProjectLayout::Root', $config);
    }

    public function test_generate_core_file(): void
    {
        $core = $this->generator->generateCoreFile('myapp\\core');

        self::assertStringContainsString('(ns myapp\\core)', $core);
        self::assertStringContainsString('(defn greet [name]', $core);
        self::assertStringContainsString('(defn main []', $core);
        self::assertStringContainsString('println', $core);
        self::assertStringContainsString('(main)', $core);
    }

    public function test_generate_test_file(): void
    {
        $test = $this->generator->generateTestFile('myapp\\core');

        self::assertStringContainsString('(ns myapp\\core-test', $test);
        self::assertStringContainsString('phel\\test', $test);
        self::assertStringContainsString(':refer [deftest is]', $test);
        self::assertStringContainsString(':refer [greet]', $test);
        self::assertStringContainsString('(deftest test-greet', $test);
        self::assertStringContainsString('(is (= "Hello, Phel!"', $test);
    }

    public function test_generate_gitignore(): void
    {
        $gitignore = $this->generator->generateGitignore();

        self::assertStringContainsString('/vendor/', $gitignore);
        self::assertStringContainsString('/out/', $gitignore);
        self::assertStringContainsString('/src/PhelGenerated/', $gitignore);
        self::assertStringContainsString('*.phar', $gitignore);
        self::assertStringContainsString('.phpunit.result.cache', $gitignore);
        self::assertStringContainsString('phel-config-local.php', $gitignore);
    }

    public function test_generate_gitignore_for_root_layout_omits_phel_generated(): void
    {
        $gitignore = $this->generator->generateGitignore(ProjectLayout::Root);

        self::assertStringContainsString('/vendor/', $gitignore);
        self::assertStringContainsString('/out/', $gitignore);
        self::assertStringContainsString('*.phar', $gitignore);
        self::assertStringNotContainsString('/src/PhelGenerated/', $gitignore);
    }
}
