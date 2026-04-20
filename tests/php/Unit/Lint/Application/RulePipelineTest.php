<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lint\Application;

use Phel\Api\Transfer\Diagnostic;
use Phel\Api\Transfer\ProjectIndex;
use Phel\Lint\Application\Config\RuleSettings;
use Phel\Lint\Application\RulePipeline;
use Phel\Lint\Domain\FileAnalysis;
use Phel\Lint\Domain\LintRuleInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;

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

    public function test_it_isolates_failing_rule(): void
    {
        $bad = new class() implements LintRuleInterface {
            public function code(): string
            {
                return 'phel/bad';
            }

            public function apply(FileAnalysis $analysis): array
            {
                throw new RuntimeException('boom');
            }
        };
        $good = $this->ruleReturning('phel/good', [
            $this->placeholder('phel/good', 'msg'),
        ]);

        $pipeline = new RulePipeline([$bad, $good]);
        $settings = new RuleSettings(
            severities: [
                'phel/bad' => Diagnostic::SEVERITY_WARNING,
                'phel/good' => Diagnostic::SEVERITY_WARNING,
            ],
        );

        $result = $pipeline->run($this->analysis(), $settings);

        self::assertCount(1, $result);
        self::assertSame('phel/good', $result[0]->code);
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
