<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lint\Application\Rule;

use Phel\Lint\Application\Config\RuleRegistry;
use Phel\Lint\Application\Rule\DiscouragedVarRule;
use Phel\Lint\Domain\FileAnalysis;
use Phel\Shared\Api\Definition;
use Phel\Shared\Api\ProjectIndex;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

final class DiscouragedVarRuleTest extends RuleTestCase
{
    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_flags_a_use_of_a_locally_deprecated_definition(): void
    {
        $rule = new DiscouragedVarRule();
        $source = <<<'PHEL'
(defn old-thing {:deprecated "use new-thing"} [] :old)
(defn caller [] (old-thing))
PHEL;
        $analysis = $this->buildAnalysis($source);

        $diagnostics = $rule->apply($analysis);

        self::assertCount(1, $diagnostics);
        self::assertSame(RuleRegistry::DISCOURAGED_VAR, $diagnostics[0]->code);
        self::assertStringContainsString('use new-thing', $diagnostics[0]->message);
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_does_not_flag_the_deprecated_definition_itself(): void
    {
        $rule = new DiscouragedVarRule();
        $analysis = $this->buildAnalysis("(defn old-thing {:deprecated \"gone\"} [] :old)\n");

        self::assertSame([], $rule->apply($analysis));
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_does_not_flag_a_definition_whose_docstring_merely_says_deprecated(): void
    {
        $rule = new DiscouragedVarRule();
        // The docstring documents a `:deprecated` map key and a deprecated PHP
        // builtin; the definition itself is perfectly current.
        $source = <<<'PHEL'
(defn symbol-info
  "Returns keys :doc, :private, :deprecated. Avoids the deprecated PHP builtin."
  []
  {})
(defn caller [] (symbol-info))
PHEL;
        $analysis = $this->buildAnalysis($source);

        self::assertSame([], $rule->apply($analysis));
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_flags_a_use_of_a_definition_deprecated_elsewhere_in_the_project(): void
    {
        $rule = new DiscouragedVarRule();
        $analysis = $this->withProjectIndex(
            "(defn caller [] (set-meta! {} {}))\n",
            new Definition(
                namespace: 'phel\core',
                name: 'set-meta!',
                uri: 'core.phel',
                line: 1,
                col: 0,
                kind: Definition::KIND_DEF,
                signature: [],
                docstring: 'Sets the metadata to a given object.',
                private: false,
                deprecated: '0.32.0',
            ),
        );

        $diagnostics = $rule->apply($analysis);

        self::assertCount(1, $diagnostics);
        self::assertStringContainsString("'set-meta!'", $diagnostics[0]->message);
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_does_not_flag_a_project_definition_that_is_not_deprecated(): void
    {
        $rule = new DiscouragedVarRule();
        $analysis = $this->withProjectIndex(
            "(defn caller [] (with-meta {} {}))\n",
            new Definition(
                namespace: 'phel\core',
                name: 'with-meta',
                uri: 'core.phel',
                line: 1,
                col: 0,
                kind: Definition::KIND_DEF,
                signature: [],
                docstring: 'Replaces the deprecated set-meta!.',
                private: false,
            ),
        );

        self::assertSame([], $rule->apply($analysis));
    }

    private function withProjectIndex(string $source, Definition $definition): FileAnalysis
    {
        $analysis = $this->buildAnalysis($source);

        return new FileAnalysis(
            uri: $analysis->uri,
            namespace: $analysis->namespace,
            source: $analysis->source,
            forms: $analysis->forms,
            projectIndex: new ProjectIndex([$definition->fullName() => $definition]),
        );
    }
}
