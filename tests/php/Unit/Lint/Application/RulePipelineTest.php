<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lint\Application;

use Phel\Lint\Application\Config\RuleRegistry;
use Phel\Lint\Application\Config\RuleSettings;
use Phel\Lint\Application\RulePipeline;
use Phel\Lint\Domain\FileAnalysis;
use Phel\Lint\Domain\LintRuleInterface;
use Phel\Lint\Transfer\LintResult;
use Phel\Shared\Api\Diagnostic;
use Phel\Shared\Api\ProjectIndex;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function array_filter;
use function array_map;
use function array_values;

final class RulePipelineTest extends TestCase
{
    public function test_it_skips_rules_set_to_off(): void
    {
        $rule = $this->ruleReturning('phel/a', [
            $this->placeholder('phel/a', 'x'),
        ]);
        $pipeline = new RulePipeline([$rule]);

        $settings = new RuleSettings(
            severities: ['phel/a' => RuleSettings::SEVERITY_OFF],
        );

        $result = $pipeline->run($this->analysis(), $settings);

        self::assertSame([], $result);
    }

    public function test_it_rewrites_severity_from_settings(): void
    {
        $rule = $this->ruleReturning('phel/a', [
            $this->placeholder('phel/a', 'x'),
        ]);
        $pipeline = new RulePipeline([$rule]);

        $settings = new RuleSettings(
            severities: ['phel/a' => Diagnostic::SEVERITY_ERROR],
        );

        $result = $pipeline->run($this->analysis(), $settings);

        self::assertCount(1, $result);
        self::assertSame(Diagnostic::SEVERITY_ERROR, $result[0]->severity);
    }

    public function test_it_reports_a_crashing_rule_as_an_internal_error(): void
    {
        $result = $this->runWithCrashingRule(Diagnostic::SEVERITY_WARNING);

        $internal = $this->onlyInternalError($result);

        self::assertSame(RuleRegistry::INTERNAL_ERROR, $internal->code);
        self::assertStringContainsString("Lint rule 'phel/bad' crashed", $internal->message);
        self::assertStringContainsString('RuntimeException: boom', $internal->message);
    }

    /**
     * A crash is a fact about the linter, so the rule's configured severity
     * must not grade it. Honouring `:warning` here would leave `phel lint`
     * exiting 0 with the rule's real findings missing.
     */
    public function test_a_crash_is_an_error_whatever_severity_the_rule_is_configured_with(): void
    {
        foreach ([Diagnostic::SEVERITY_WARNING, Diagnostic::SEVERITY_INFO, Diagnostic::SEVERITY_HINT] as $configured) {
            $internal = $this->onlyInternalError($this->runWithCrashingRule($configured));

            self::assertSame(
                Diagnostic::SEVERITY_ERROR,
                $internal->severity,
                'Configured severity: ' . $configured,
            );
        }
    }

    public function test_a_crashing_rule_does_not_suppress_its_siblings(): void
    {
        $result = $this->runWithCrashingRule(Diagnostic::SEVERITY_WARNING);

        $codes = array_map(static fn(Diagnostic $d): string => $d->code, $result);

        self::assertContains('phel/good', $codes);
        self::assertContains(RuleRegistry::INTERNAL_ERROR, $codes);
    }

    /**
     * `LintCommand` maps `hasErrors()` straight onto exit code 1, so this is
     * the assertion that the run can no longer come back clean.
     */
    public function test_a_crash_makes_the_result_fail(): void
    {
        $result = new LintResult($this->runWithCrashingRule(Diagnostic::SEVERITY_WARNING));

        self::assertTrue($result->hasErrors());
    }

    public function test_a_rule_switched_off_never_runs_so_it_cannot_crash(): void
    {
        $pipeline = new RulePipeline([$this->crashingRule()]);
        $settings = new RuleSettings(severities: ['phel/bad' => RuleSettings::SEVERITY_OFF]);

        self::assertSame([], $pipeline->run($this->analysis(), $settings));
    }

    public function test_it_respects_per_rule_excludes(): void
    {
        $rule = $this->ruleReturning('phel/a', [
            $this->placeholder('phel/a', 'x'),
        ]);
        $pipeline = new RulePipeline([$rule]);

        $settings = new RuleSettings(
            severities: ['phel/a' => Diagnostic::SEVERITY_WARNING],
            excludeGlobs: ['phel/a' => ['*excluded*']],
        );

        $analysis = new FileAnalysis(
            uri: '/path/to/excluded/file.phel',
            namespace: 'phel\\test',
            source: '',
            forms: [],
            projectIndex: new ProjectIndex([], []),
        );

        self::assertSame([], $pipeline->run($analysis, $settings));
    }

    /**
     * @return list<Diagnostic>
     */
    private function runWithCrashingRule(string $configuredSeverity): array
    {
        $pipeline = new RulePipeline([
            $this->crashingRule(),
            $this->ruleReturning('phel/good', [$this->placeholder('phel/good', 'msg')]),
        ]);

        return $pipeline->run($this->analysis(), new RuleSettings(
            severities: [
                'phel/bad' => $configuredSeverity,
                'phel/good' => Diagnostic::SEVERITY_WARNING,
            ],
        ));
    }

    /**
     * @param list<Diagnostic> $result
     */
    private function onlyInternalError(array $result): Diagnostic
    {
        $internal = array_values(array_filter(
            $result,
            static fn(Diagnostic $d): bool => $d->code === RuleRegistry::INTERNAL_ERROR,
        ));

        self::assertCount(1, $internal);

        return $internal[0];
    }

    private function crashingRule(): LintRuleInterface
    {
        return new class() implements LintRuleInterface {
            public function code(): string
            {
                return 'phel/bad';
            }

            public function apply(FileAnalysis $analysis): array
            {
                throw new RuntimeException('boom');
            }
        };
    }

    private function analysis(): FileAnalysis
    {
        return new FileAnalysis(
            uri: 'f.phel',
            namespace: 'user',
            source: '',
            forms: [],
            projectIndex: new ProjectIndex([], []),
        );
    }

    private function placeholder(string $code, string $message): Diagnostic
    {
        return new Diagnostic($code, Diagnostic::SEVERITY_WARNING, $message, 'f.phel', 1, 1, 1, 1);
    }

    /**
     * @param list<Diagnostic> $output
     */
    private function ruleReturning(string $code, array $output): LintRuleInterface
    {
        return new readonly class($code, $output) implements LintRuleInterface {
            /**
             * @param list<Diagnostic> $output
             */
            public function __construct(
                private string $ruleCode,
                private array $output,
            ) {}

            public function code(): string
            {
                return $this->ruleCode;
            }

            public function apply(FileAnalysis $analysis): array
            {
                return $this->output;
            }
        };
    }
}
