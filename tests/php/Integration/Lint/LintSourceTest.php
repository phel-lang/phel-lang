<?php

declare(strict_types=1);

namespace PhelTest\Integration\Lint;

use Phel;
use Phel\Compiler\CompilerFacade;
use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;
use Phel\Lint\Application\Cache\LintCache;
use Phel\Lint\Application\Config\RuleSettings;
use Phel\Lint\Application\FileCollector;
use Phel\Lint\Application\LintRunner;
use Phel\Lint\Application\Rule\UnusedBindingRule;
use Phel\Lint\Application\RulePipeline;
use Phel\Lint\Application\SourceReader;
use Phel\Lint\LintFacade;
use Phel\Shared\Api\Diagnostic;
use Phel\Shared\Facade\ApiFacadeInterface;
use Phel\Shared\LintRuleCodes;
use PHPUnit\Framework\TestCase;

final class LintSourceTest extends TestCase
{
    protected function setUp(): void
    {
        Phel::bootstrap(__DIR__);
        GlobalEnvironmentSingleton::initializeNew();
    }

    public function test_source_lint_uses_text_even_when_the_path_does_not_exist(): void
    {
        $path = sys_get_temp_dir() . '/phel-unsaved-' . bin2hex(random_bytes(6)) . '.phel';
        $settings = new RuleSettings([LintRuleCodes::UNUSED_BINDING => Diagnostic::SEVERITY_WARNING]);
        $result = new LintFacade()->lintSource("(ns buffer)\n\n(let [buffer-only 1] :ok)", $path, $settings);

        self::assertCount(1, $result->diagnostics);
        self::assertSame(3, $result->diagnostics[0]->startLine);
        self::assertStringContainsString('buffer-only', $result->diagnostics[0]->message);
        self::assertSame($path, $result->diagnostics[0]->uri);
        self::assertFileDoesNotExist($path);
    }

    public function test_unsaved_lines_and_names_replace_the_saved_source(): void
    {
        $directory = sys_get_temp_dir() . '/phel-lint-source-' . bin2hex(random_bytes(6));
        mkdir($directory);
        $path = $directory . '/saved.phel';
        $saved = "(ns buffer)\n(let [saved 1] :ok)";
        file_put_contents($path, $saved);
        try {
            $settings = new RuleSettings([LintRuleCodes::UNUSED_BINDING => Diagnostic::SEVERITY_WARNING]);
            $result = new LintFacade()->lintSource("(ns buffer)\n\n\n(let [unsaved 1] :ok)", $path, $settings);
            self::assertCount(1, $result->diagnostics);
            self::assertSame(4, $result->diagnostics[0]->startLine);
            self::assertStringContainsString('unsaved', $result->diagnostics[0]->message);
            self::assertSame($saved, file_get_contents($path));
        } finally {
            unlink($path);
            rmdir($directory);
        }
    }

    public function test_source_lint_preserves_the_path_for_rule_exclusions(): void
    {
        $settings = new RuleSettings([LintRuleCodes::UNUSED_BINDING => Diagnostic::SEVERITY_WARNING], [LintRuleCodes::UNUSED_BINDING => ['/project/*.phel']]);
        $result = new LintFacade()->lintSource("(ns buffer)\n(let [unused 1] :ok)", '/project/unsaved.phel', $settings);
        self::assertSame([], $result->diagnostics);
    }

    public function test_run_source_neither_reuses_nor_replaces_disk_cache_results(): void
    {
        $directory = sys_get_temp_dir() . '/phel-lint-cache-source-' . bin2hex(random_bytes(6));
        mkdir($directory);
        $path = $directory . '/saved.phel';
        file_put_contents($path, '(ns cached)');
        try {
            $cache = new LintCache($directory, 'source-test');
            $cache->put($path, []);
            $api = $this->createStub(ApiFacadeInterface::class);
            $api->method('analyzeSource')->willReturn([]);
            $runner = new LintRunner($api, new FileCollector(), new SourceReader(new CompilerFacade()), new RulePipeline([new UnusedBindingRule()]), $cache);
            $settings = new RuleSettings([LintRuleCodes::UNUSED_BINDING => Diagnostic::SEVERITY_WARNING]);
            $result = $runner->runSource("(ns cached)\n(let [unsaved 1] :ok)", $path, $settings);
            self::assertCount(1, $result->diagnostics);
            self::assertSame([], $cache->get($path));
            self::assertFileDoesNotExist($directory . '/index.json');
        } finally {
            unlink($path);
            rmdir($directory);
        }
    }

}
