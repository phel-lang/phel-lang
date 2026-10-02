<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lint\Application\Rule;

use Phel\Lint\Application\Rule\UnresolvedNamespaceRule;
use Phel\Lint\Domain\KnownNamespacesInterface;
use Phel\Shared\Api\Diagnostic;
use Phel\Shared\LintRuleCodes;

use function array_map;

final class UnresolvedNamespaceRuleTest extends RuleTestCase
{
    public function test_it_flags_a_misspelled_phel_require_with_a_suggestion(): void
    {
        $diagnostics = $this->rule()->apply($this->buildAnalysis("(ns app.main\n  (:require phel.strng :as s))\n"));

        self::assertCount(1, $diagnostics);
        self::assertSame(LintRuleCodes::UNRESOLVED_NAMESPACE, $diagnostics[0]->code);
        self::assertSame("Cannot find namespace 'phel.strng'. Did you mean 'phel.string'?", $diagnostics[0]->message);
        self::assertSame(2, $diagnostics[0]->startLine);
        self::assertSame(12, $diagnostics[0]->startCol);
    }

    public function test_it_flags_vector_entries_and_names_without_a_close_match(): void
    {
        $analysis = $this->buildAnalysis(
            "(ns app.main\n  (:require [phel.str :as s] [phel.nonexistent :as nx]))\n",
        );

        self::assertSame(
            [
                "Cannot find namespace 'phel.str'. Did you mean 'phel.string'?",
                "Cannot find namespace 'phel.nonexistent'.",
            ],
            $this->messages($this->rule()->apply($analysis)),
        );
    }

    public function test_a_clojure_require_resolves_through_its_phel_target(): void
    {
        $analysis = $this->buildAnalysis(
            "(ns app.main\n  (:require [clojure.set :as set] [clojure.string :as str] [clojure.nothere :as n]))\n",
        );

        self::assertSame(
            ["Cannot find namespace 'clojure.nothere'."],
            $this->messages($this->rule()->apply($analysis)),
        );
    }

    public function test_it_leaves_shipped_and_user_namespaces_alone(): void
    {
        $analysis = $this->buildAnalysis(
            "(ns app.main\n  (:require phel.json :refer [encode] [app.missing :as m] clojure.core-test.portability))\n",
        );

        self::assertSame([], $this->rule(['phel.core', 'phel.json', 'clojure.core-test.portability'])->apply($analysis));
    }

    /**
     * @param list<string> $known
     */
    private function rule(array $known = ['phel.core', 'phel.string', 'phel.json', 'phel.test']): UnresolvedNamespaceRule
    {
        return new UnresolvedNamespaceRule(new readonly class($known) implements KnownNamespacesInterface {
            /**
             * @param list<string> $known
             */
            public function __construct(private array $known) {}

            public function all(): array
            {
                return $this->known;
            }
        });
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
