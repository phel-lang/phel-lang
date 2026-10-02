<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lint\Application\Rule;

use Phel\Lint\Application\Rule\UnknownClassRule;
use Phel\Shared\Api\Diagnostic;
use Phel\Shared\Exceptions\Hint\ClassNotFoundHint;
use Phel\Shared\LintRuleCodes;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

use function array_map;

final class UnknownClassRuleTest extends RuleTestCase
{
    public function test_it_never_runs_an_autoloader(): void
    {
        $called = [];
        $autoloader = static function (string $class) use (&$called): void {
            $called[] = $class;
        };
        spl_autoload_register($autoloader);

        try {
            $diagnostics = $this->rule()->apply($this->buildAnalysis("(ns app)\n(Lint.Probe.NeverLoaded/run)\n"));
        } finally {
            spl_autoload_unregister($autoloader);
        }

        self::assertSame([], $called);
        self::assertCount(1, $diagnostics);
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_flags_a_java_class_call_with_its_phel_replacement(): void
    {
        $diagnostics = $this->rule()->apply($this->buildAnalysis("(ns app)\n(Integer/parseInt \"3\")\n"));

        self::assertCount(1, $diagnostics);
        self::assertSame(LintRuleCodes::UNKNOWN_CLASS, $diagnostics[0]->code);
        self::assertSame(
            "Class 'Integer' in 'Integer/parseInt' cannot be autoloaded. Integer is a Java class, not a PHP one: for Integer/parseInt use (parse-long s).",
            $diagnostics[0]->message,
        );
        self::assertSame(2, $diagnostics[0]->startLine);
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_flags_any_class_that_cannot_be_autoloaded(): void
    {
        $diagnostics = $this->rule()->apply($this->buildAnalysis("(ns app)\n(Foo.Bar/baz 1)\n(\\Missing/thing)\n"));

        self::assertSame(
            ["Class 'Foo\\Bar' in 'Foo.Bar/baz' cannot be autoloaded.", "Class 'Missing' in '\\Missing/thing' cannot be autoloaded."],
            $this->messages($diagnostics),
        );
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_resolves_use_aliases_and_skips_loadable_classes(): void
    {
        $source = <<<'PHEL'
            (ns app
              (:use DateTimeImmutable)
              (:use Phel.Lang.Keyword :as K))
            (DateTimeImmutable/createFromFormat "Y" "2020")
            (K/create "a")
            (\DateTime/createFromFormat "Y" "2020")
            PHEL;

        self::assertSame([], $this->rule()->apply($this->buildAnalysis($source)));
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_skips_phel_namespaces_declared_classes_and_quoted_forms(): void
    {
        $source = <<<'PHEL'
            (ns app
              (:require phel.string :as S))
            (defstruct Point [x y])
            (S/upper-case "a")
            (Point/thing 1)
            (phel.string/upper-case "a")
            '(Thread/sleep 1)
            PHEL;

        self::assertSame([], $this->rule()->apply($this->buildAnalysis($source)));
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_resolves_a_top_level_use_in_a_file_opened_with_in_ns(): void
    {
        $source = <<<'PHEL'
            (in-ns phel.core)
            (use Phel.Lang.Keyword)
            (Keyword/create "a")
            PHEL;

        self::assertSame([], $this->rule()->apply($this->buildAnalysis($source)));
    }

    private function rule(): UnknownClassRule
    {
        return new UnknownClassRule(new ClassNotFoundHint());
    }

    /**
     * @param list<Diagnostic> $diagnostics
     *
     * @return list<string>
     */
    private function messages(array $diagnostics): array
    {
        return array_map(static fn(Diagnostic $d): string => $d->message, $diagnostics);
    }
}
