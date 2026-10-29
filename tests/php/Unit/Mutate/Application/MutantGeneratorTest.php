<?php

declare(strict_types=1);

namespace PhelTest\Unit\Mutate\Application;

use Gacela\Framework\Gacela;
use Iterator;
use Phel\Compiler\CompilerFacade;
use Phel\Mutate\Application\MutantGenerator;
use Phel\Mutate\Domain\Mutant;
use Phel\Mutate\Domain\Mutator\ArithmeticMutator;
use Phel\Mutate\Domain\Mutator\BodyDropMutator;
use Phel\Mutate\Domain\Mutator\ConditionMutator;
use Phel\Mutate\Domain\Mutator\MutatorInterface;
use Phel\Mutate\Domain\Mutator\Nodes;
use Phel\Mutate\Domain\Mutator\Replacement;
use Phel\Mutate\Domain\Mutator\ReturnNilMutator;
use Phel\Shared\Parser\Node\InnerNodeInterface;
use Phel\Shared\Parser\Node\NodeInterface;
use PHPUnit\Framework\Attributes\DataProvider;
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
        self::assertSame(4, $first->column, 'the `+` in `  (+ a b))`, 1-based as in every CLI output');
        self::assertSame('arith', $first->mutator);
        self::assertSame("(defn add\n  \"Adds.\"\n  [a b]\n  (+ a b))", $first->originalForm);
        self::assertSame("(defn add\n  \"Adds.\"\n  [a b]\n  (- a b))", $first->mutatedForm);
    }

    public function test_mutates_selected_conditional_definitions_and_preserves_other_branches(): void
    {
        $source = '(ns app.calc) #?(:phel (defn add [a] (+ a 1)) :clj (defn add [a] (* a 2)))';
        $mutants = $this->generate($source, [new ArithmeticMutator()]);
        self::assertCount(1, $mutants);
        self::assertSame('#?(:phel (defn add [a] (- a 1)) :clj (defn add [a] (* a 2)))', $mutants[0]->mutatedForm);
    }

    public function test_mutates_selected_conditional_descendants_and_splices(): void
    {
        $source = '(ns app.calc) (defn add [a] #?(:phel (+ a 1) :clj (* a 2)) [#?@(:phel [(+ a 3)] :clj [(* a 4)])])';
        $mutants = $this->generate($source, [new ArithmeticMutator()]);
        self::assertCount(2, $mutants);
        self::assertSame('(defn add [a] #?(:phel (- a 1) :clj (* a 2)) [#?@(:phel [(+ a 3)] :clj [(* a 4)])])', $mutants[0]->mutatedForm);
        self::assertSame('(defn add [a] #?(:phel (+ a 1) :clj (* a 2)) [#?@(:phel [(- a 3)] :clj [(* a 4)])])', $mutants[1]->mutatedForm);
    }

    #[DataProvider('conditionalStructureCases')]
    public function test_mutates_conditional_definition_structures(string $form): void
    {
        $mutants = $this->generate('(ns #?(:phel app.calc :clj other)) ' . $form, [new ArithmeticMutator()]);
        self::assertCount(1, $mutants);
        self::assertSame('f', $mutants[0]->definition);
        self::assertSame('app.calc', $mutants[0]->namespace);
        self::assertSame(str_replace('(+ x 1)', '(- x 1)', $form), $mutants[0]->mutatedForm);
    }

    public static function conditionalStructureCases(): Iterator
    {
        yield 'head' => ['(#?(:phel defn :clj defn-) f [x] (+ x 1))'];
        yield 'name' => ['(defn #?(:phel f :clj g) [x] (+ x 1))'];
        yield 'params' => ['(defn f #?(:phel [x] :clj [y]) (+ x 1))'];
        yield 'docstring' => ['(defn f #?(:phel "Doc" :clj "Other") [x] (+ x 1))'];
        yield 'attrs' => ['(defn f #?(:phel {:doc "Doc"} :clj {}) [x] (+ x 1))'];
        yield 'metadata' => ['(defn #?(:phel ^:private f :clj g) [x] (+ x 1))'];
        yield 'arity' => ['(defn f #?(:phel ([x] (+ x 1)) :clj ([y] (* y 2))))'];
        yield 'arity params' => ['(defn f (#?(:phel [x] :clj [y]) (+ x 1)))'];
        yield 'spliced params' => ['(defn f #?@(:phel [[x]] :clj [[y]]) (+ x 1))'];
        yield 'spliced arity' => ['(defn f #?@(:phel [([x] (+ x 1))] :clj [([y] (* y 2))]))'];
    }

    public function test_body_mutators_recognize_conditional_declaration_structure(): void
    {
        $source = '(ns app.calc) (#?(:phel defn :clj defn-) f #?(:phel "Doc" :clj "Other") #?(:phel [x] :clj [y]) (println x) (+ x 1))';
        $mutants = $this->generate($source, [new ReturnNilMutator(), new BodyDropMutator()]);
        self::assertCount(3, $mutants);
    }

    public function test_return_mutator_keeps_definition_context_for_conditional_body(): void
    {
        $form = '(defn f [] #?(:phel 1 :clj 2))';
        $mutants = $this->generate('(ns app.calc) ' . $form, [new ReturnNilMutator()]);
        self::assertCount(1, $mutants);
        self::assertSame('(defn f [] #?(:phel nil :clj 2))', $mutants[0]->mutatedForm);
    }

    public function test_body_drop_keeps_definition_context_for_conditional_body(): void
    {
        $form = '(defn f [] #?(:phel 1 :clj 2) 3)';
        $mutants = $this->generate('(ns app.calc) ' . $form, [new BodyDropMutator()]);
        self::assertCount(2, $mutants);
        self::assertSame('(defn f [] 3)', $mutants[0]->mutatedForm);
        self::assertSame('(defn f [] #?(:phel 1 :clj 2))', $mutants[1]->mutatedForm);
    }

    public function test_condition_mutator_preserves_splice_branches(): void
    {
        $form = '(defn f [x] (if #?@(:phel [x 1 2] :clj [x 3 4])))';
        $mutants = $this->generate('(ns app.calc) ' . $form, [new ConditionMutator()]);
        self::assertCount(1, $mutants);
        self::assertSame('(defn f [x] (if #?@(:phel [(not x) 1 2] :clj [x 3 4])))', $mutants[0]->mutatedForm);
    }

    public function test_body_drop_deletes_only_selected_splice_element(): void
    {
        $form = '(defn f [] #?@(:phel [1 2] :clj [3 4]))';
        $mutants = $this->generate('(ns app.calc) ' . $form, [new BodyDropMutator()]);
        self::assertCount(2, $mutants);
        self::assertSame('(defn f [] #?@(:phel [ 2] :clj [3 4]))', $mutants[0]->mutatedForm);
        self::assertSame('(defn f [] #?@(:phel [1] :clj [3 4]))', $mutants[1]->mutatedForm);
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
