<?php

declare(strict_types=1);

namespace PhelTest\Unit\Mutate\Domain\Mutator;

use Gacela\Framework\Gacela;
use Phel\Compiler\CompilerFacade;
use Phel\Mutate\Application\MutantGenerator;
use Phel\Mutate\Domain\Mutant;
use Phel\Mutate\Domain\Mutator\ConditionMutator;
use PHPUnit\Framework\TestCase;

use function array_map;

final class ConditionMutatorTest extends TestCase
{
    protected function setUp(): void
    {
        Gacela::bootstrap(__DIR__);
    }

    public function test_it_is_identified_as_cond_branch(): void
    {
        self::assertSame('cond-branch', new ConditionMutator()->id());
    }

    public function test_every_conditional_head_gets_its_test_negated(): void
    {
        $source = <<<'PHEL'
        (ns app.x)
        (defn f [a]
          (if a 1 2)
          (if-not a 1 2)
          (when a 1)
          (when-not a 1))
        PHEL;

        self::assertSame(
            [
                '(if a 1 2) -> (if (not a) 1 2)',
                '(if-not a 1 2) -> (if-not (not a) 1 2)',
                '(when a 1) -> (when (not a) 1)',
                '(when-not a 1) -> (when-not (not a) 1)',
            ],
            $this->descriptions($source),
        );
    }

    public function test_a_compound_test_keeps_its_own_layout_inside_the_wrapper(): void
    {
        $mutants = $this->generate("(ns app.x)\n(defn f [a b]\n  (if (< a b)\n    :less\n    :more))\n");

        self::assertCount(1, $mutants);
        self::assertSame('cond-branch', $mutants[0]->mutator);
        self::assertSame("(defn f [a b]\n  (if (not (< a b))\n    :less\n    :more))", $mutants[0]->mutatedForm);
    }

    public function test_a_nested_conditional_is_its_own_site(): void
    {
        $source = <<<'PHEL'
        (ns app.x)
        (defn f [a b]
          (if a
            (when b :both)
            :neither))
        PHEL;

        self::assertSame(
            [
                "(if a\n    (when b :both)\n    :neither) -> (if (not a)\n    (when b :both)\n    :neither)",
                '(when b :both) -> (when (not b) :both)',
            ],
            $this->descriptions($source),
        );
    }

    public function test_an_already_negated_test_is_not_wrapped_twice(): void
    {
        self::assertSame([], $this->descriptions("(ns app.x)\n(defn f [a] (if (not a) 1 2))\n"));
    }

    public function test_forms_that_are_not_conditionals_have_no_test_to_negate(): void
    {
        $source = <<<'PHEL'
        (ns app.x)
        (defn f [a]
          (do a)
          (cond a 1 :else 2)
          (case a 1 :one :other)
          [(if a 1 2)])
        PHEL;

        self::assertSame(['(if a 1 2) -> (if (not a) 1 2)'], $this->descriptions($source));
    }

    /**
     * @return list<string>
     */
    private function descriptions(string $source): array
    {
        return array_map(static fn(Mutant $m): string => $m->description, $this->generate($source));
    }

    /**
     * @return list<Mutant>
     */
    private function generate(string $source): array
    {
        return new MutantGenerator(new CompilerFacade(), [new ConditionMutator()])
            ->generate('/src/x.phel', $source);
    }
}
