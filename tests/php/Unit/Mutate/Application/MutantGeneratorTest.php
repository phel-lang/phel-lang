<?php

declare(strict_types=1);

namespace PhelTest\Unit\Mutate\Application;

use Gacela\Framework\Gacela;
use Phel\Compiler\CompilerFacade;
use Phel\Mutate\Application\MutantGenerator;
use Phel\Mutate\Domain\Mutant;
use Phel\Mutate\Domain\Mutator\ArithmeticMutator;
use Phel\Mutate\Domain\Mutator\MutatorInterface;
use Phel\Mutate\Domain\Mutator\Nodes;
use Phel\Mutate\Domain\Mutator\Replacement;
use Phel\Shared\Parser\Node\InnerNodeInterface;
use Phel\Shared\Parser\Node\NodeInterface;
use PHPUnit\Framework\TestCase;

use function array_map;

final class MutantGeneratorTest extends TestCase
{
    protected function setUp(): void
    {
        Gacela::bootstrap(__DIR__);
    }

    public function test_mutates_only_inside_defn_bodies_and_reemits_the_whole_form(): void
    {
        $source = <<<'PHEL'
        (ns app.calc
          (:require phel.string :as s))

        ;; leading comment
        (def limit (+ 1 2))

        (defn add
          "Adds."
          [a b]
          (+ a b))

        (defn- helper [x] (- x 1))

        (defmacro twice [x] `(+ ~x ~x))
        PHEL;

        $mutants = $this->generate($source, [new ArithmeticMutator()]);

        self::assertSame(
            [
                'add: (+ a b) -> (- a b)',
                'helper: (- x 1) -> (+ x 1)',
            ],
            array_map(static fn(Mutant $m): string => $m->definition . ': ' . $m->description, $mutants),
        );

        $first = $mutants[0];
        self::assertSame('app.calc', $first->namespace);
        self::assertSame('/src/calc.phel', $first->file);
        self::assertSame(10, $first->line);
        self::assertSame('arith', $first->mutator);
        self::assertSame("(defn add\n  \"Adds.\"\n  [a b]\n  (+ a b))", $first->originalForm);
        self::assertSame("(defn add\n  \"Adds.\"\n  [a b]\n  (- a b))", $first->mutatedForm);
    }

    public function test_the_tree_is_left_untouched_after_generating(): void
    {
        $source = "(ns app.x)\n(defn f [a] (+ a 1))\n";
        $mutants = $this->generate($source, [new ArithmeticMutator()]);

        self::assertCount(1, $mutants);
        // A second run over the same source yields the same mutants: no
        // replacement leaked into the parse tree between sites.
        self::assertEquals($mutants, $this->generate($source, [new ArithmeticMutator()]));
    }

    public function test_quoted_data_params_docstrings_and_the_head_are_not_mutation_sites(): void
    {
        $source = <<<'PHEL'
        (ns app.x)
        (defn f
          {:doc "+"}
          [+ & more]
          '(+ 1 2)
          `(+ 3 4)
          (+ 5 6))
        PHEL;

        $mutants = $this->generate($source, [new ArithmeticMutator()]);

        self::assertSame(['(+ 5 6) -> (- 5 6)'], array_map(static fn(Mutant $m): string => $m->description, $mutants));
    }

    public function test_multi_arity_bodies_are_walked_but_their_parameter_vectors_are_not(): void
    {
        $source = "(ns app.x)\n(defn f\n  ([a] (+ a 1))\n  ([a b] (+ a b)))\n";

        $mutants = $this->generate($source, [new ArithmeticMutator()]);

        self::assertSame(
            ['(+ a 1) -> (- a 1)', '(+ a b) -> (- a b)'],
            array_map(static fn(Mutant $m): string => $m->description, $mutants),
        );
    }

    public function test_a_mutator_may_remove_a_child(): void
    {
        $dropSecondBodyForm = new class() implements MutatorInterface {
            public function id(): string
            {
                return 'drop';
            }

            public function mutate(InnerNodeInterface $parent, int $index, NodeInterface $child): array
            {
                if (Nodes::symbolName($child) !== 'println') {
                    return [];
                }

                return [new Replacement(Nodes::withoutChild($parent->getChildren(), $index), 'drop println')];
            }
        };
        $source = "(ns app.x)\n(defn f [a]\n  (println a)\n  a)\n";

        $mutants = $this->generate($source, [$dropSecondBodyForm]);

        // `println` is the head of `(println a)`; removing that symbol from
        // its list is a legal (if odd) mutation and must round-trip cleanly.
        self::assertCount(1, $mutants);
        self::assertSame("(defn f [a]\n  ( a)\n  a)", $mutants[0]->mutatedForm);
    }

    public function test_a_secondary_file_joining_a_namespace_with_in_ns_lands_its_mutants_there(): void
    {
        $plain = $this->generate("(in-ns phel.core)\n(defn f [a] (+ a 1))\n", [new ArithmeticMutator()]);
        $quoted = $this->generate("(in-ns 'app.core)\n(defn f [a] (+ a 1))\n", [new ArithmeticMutator()]);

        self::assertSame('phel.core', $plain[0]->namespace);
        self::assertSame('app.core', $quoted[0]->namespace);
    }

    /**
     * @param list<MutatorInterface> $mutators
     *
     * @return list<Mutant>
     */
    private function generate(string $source, array $mutators): array
    {
        $compiler = new CompilerFacade();

        return new MutantGenerator($compiler, $mutators)->generate('/src/calc.phel', $source);
    }
}
