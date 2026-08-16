<?php

declare(strict_types=1);

namespace PhelTest\Unit\Mutate\Domain\Mutator;

use Gacela\Framework\Gacela;
use Phel\Compiler\CompilerFacade;
use Phel\Mutate\Application\MutantGenerator;
use Phel\Mutate\Domain\Mutant;
use Phel\Mutate\Domain\Mutator\ReturnNilMutator;
use PHPUnit\Framework\TestCase;

use function array_map;

final class ReturnNilMutatorTest extends TestCase
{
    protected function setUp(): void
    {
        Gacela::bootstrap(__DIR__);
    }

    public function test_the_returned_form_of_a_definition_becomes_nil(): void
    {
        $mutants = $this->generate("(ns app.x)\n(defn f [a]\n  (println a)\n  (+ a 1))\n");

        self::assertCount(1, $mutants);
        self::assertSame('return-nil', $mutants[0]->mutator);
        self::assertSame("(defn f [a]\n  (println a)\n  nil)", $mutants[0]->mutatedForm);
        self::assertSame(
            '(+ a 1) -> nil',
            $mutants[0]->description,
        );
    }

    public function test_a_definition_that_already_returns_nil_is_left_alone(): void
    {
        self::assertSame([], $this->descriptions("(ns app.x)\n(defn f [a]\n  (println a)\n  nil)\n"));
    }

    public function test_each_arity_of_a_multi_arity_definition_is_a_body(): void
    {
        $source = "(ns app.x)\n(defn f\n  ([a] (+ a 1))\n  ([a b] (+ a b)))\n";

        self::assertSame(
            ['(+ a 1) -> nil', '(+ a b) -> nil'],
            $this->descriptions($source),
        );
    }

    public function test_the_last_form_of_an_inner_list_is_not_a_return_value(): void
    {
        // The `do` block is the definition's return value, so it is replaced
        // as a whole; `(+ a 1)` inside it is not a body of its own.
        $source = "(ns app.x)\n(defn f [a] (do (println a) (+ a 1)))\n";

        self::assertSame(
            ['(do (println a) (+ a 1)) -> nil'],
            $this->descriptions($source),
        );
    }

    public function test_a_body_vector_is_not_mistaken_for_a_parameter_list(): void
    {
        $source = "(ns app.x)\n(defn f [a] [[a] a])\n";

        self::assertSame(
            ['[[a] a] -> nil'],
            $this->descriptions($source),
        );
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
        $generator = new MutantGenerator(new CompilerFacade(), [new ReturnNilMutator()]);

        return $generator->generate('/src/x.phel', $source);
    }
}
