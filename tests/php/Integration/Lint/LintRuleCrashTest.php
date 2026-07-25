<?php

declare(strict_types=1);

namespace PhelTest\Integration\Lint;

use Phel;
use Phel\Api\ApiFacade;
use Phel\Compiler\CompilerFacade;
use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;
use Phel\Lang\Symbol;
use Phel\Lint\Application\Cache\LintCache;
use Phel\Lint\Application\Config\RuleRegistry;
use Phel\Lint\Application\Config\RuleSettings;
use Phel\Lint\Application\FileCollector;
use Phel\Lint\Application\LintRunner;
use Phel\Lint\Application\Rule\CommentStyleRule;
use Phel\Lint\Application\RulePipeline;
use Phel\Lint\Application\SourceReader;
use Phel\Lint\Domain\FileAnalysis;
use Phel\Lint\Domain\LintRuleInterface;
use Phel\Lint\Transfer\LintResult;
use Phel\Shared\Api\Diagnostic;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function array_map;
use function array_unshift;
use function md5;
use function realpath;
use function sys_get_temp_dir;
use function uniqid;

/**
 * A rule that throws used to be skipped in silence, so `phel lint` printed
 * "No lint issues found." and exited 0 with that rule's findings missing.
 * These cases pin the replacement contract end to end, over the real reader
 * and analyzer, with a crashing test double standing in for a buggy rule.
 */
final class LintRuleCrashTest extends TestCase
{
    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_a_crashing_rule_surfaces_and_fails_the_run(): void
    {
        $result = new LintResult($this->lintFixture()->diagnostics);

        $codes = array_map(static fn(Diagnostic $d): string => $d->code, $result->diagnostics);
        self::assertContains(RuleRegistry::INTERNAL_ERROR, $codes);

        self::assertTrue(
            $result->hasErrors(),
            'LintCommand maps hasErrors() onto exit code 1, so a crash must not exit 0',
        );
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_the_internal_error_names_the_rule_and_chains_the_throwable(): void
    {
        $result = $this->lintFixture();

        $message = '';
        foreach ($result->diagnostics as $diagnostic) {
            if ($diagnostic->code === RuleRegistry::INTERNAL_ERROR) {
                $message = $diagnostic->message;
            }
        }

        self::assertStringContainsString("Lint rule 'phel/duplicate-def' crashed", $message);
        self::assertStringContainsString('RuntimeException: rule is broken', $message);
    }

    /**
     * The sibling rule runs on the same file in the same pass: its findings
     * must survive the crash, which is why the pipeline reports rather than
     * aborting the whole run.
     */
    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_sibling_rules_still_report_on_the_same_file(): void
    {
        $codes = array_map(
            static fn(Diagnostic $d): string => $d->code,
            $this->lintFixture()->diagnostics,
        );

        self::assertContains(RuleRegistry::COMMENT_STYLE, $codes);
    }

    /**
     * Fixing a rule changes neither the file hash nor the rule fingerprint, so
     * a cached internal error would be replayed long after the bug was gone.
     */
    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_an_internal_error_is_never_written_to_the_cache(): void
    {
        $clean = $this->cache();
        $this->lintFixture($clean, withCrashingRule: false);
        self::assertNotNull($clean->get($this->fixture()), 'Control: an ordinary run does cache the file');

        $crashed = $this->cache();
        $this->lintFixture($crashed);

        self::assertNull(
            $crashed->get($this->fixture()),
            'A file whose lint run crashed must be re-linted next time, not served from cache',
        );
    }

    private function cache(): LintCache
    {
        return new LintCache(
            sys_get_temp_dir() . '/phel-lint-crash-' . uniqid('', true),
            md5('fingerprint'),
        );
    }

    private function lintFixture(?LintCache $cache = null, bool $withCrashingRule = true): LintResult
    {
        Phel::bootstrap(__DIR__);
        Phel::clear();
        Symbol::resetGen();
        GlobalEnvironmentSingleton::initializeNew();

        $compilerFacade = new CompilerFacade();

        $rules = [new CommentStyleRule($compilerFacade)];
        if ($withCrashingRule) {
            array_unshift($rules, $this->crashingRule());
        }

        $runner = new LintRunner(
            new ApiFacade(),
            new FileCollector(),
            new SourceReader($compilerFacade),
            new RulePipeline($rules),
            $cache,
        );

        return $runner->run([$this->fixture()], new RuleSettings([
            RuleRegistry::DUPLICATE_DEF => Diagnostic::SEVERITY_ERROR,
            RuleRegistry::COMMENT_STYLE => Diagnostic::SEVERITY_WARNING,
        ]));
    }

    private function fixture(): string
    {
        $path = realpath(__DIR__ . '/Fixtures/comment_style.phel');
        self::assertIsString($path);

        return $path;
    }

    /**
     * Stands in for a rule with a bug in it. Borrowing a real rule code keeps
     * the settings lookup realistic without touching a shipped rule.
     */
    private function crashingRule(): LintRuleInterface
    {
        return new class() implements LintRuleInterface {
            public function code(): string
            {
                return RuleRegistry::DUPLICATE_DEF;
            }

            public function apply(FileAnalysis $analysis): array
            {
                throw new RuntimeException('rule is broken');
            }
        };
    }
}
