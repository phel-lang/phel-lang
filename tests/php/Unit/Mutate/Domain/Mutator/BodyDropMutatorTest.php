<?php

declare(strict_types=1);

namespace PhelTest\Unit\Mutate\Domain\Mutator;

use Gacela\Framework\Gacela;
use Phel\Compiler\CompilerFacade;
use Phel\Mutate\Application\MutantGenerator;
use Phel\Mutate\Domain\Mutant;
use Phel\Mutate\Domain\Mutator\BodyDropMutator;
use PHPUnit\Framework\TestCase;

use function array_map;

final class BodyDropMutatorTest extends TestCase
{
    protected function setUp(): void
    {
        Gacela::bootstrap(__DIR__);
    }

    public function test_each_form_of_a_definition_body_is_dropped_in_turn(): void
    {
        $mutants = $this->generate("(ns app.x)\n(defn f [a]\n  (println a)\n  (+ a 1))\n");

        self::assertCount(2, $mutants);
        self::assertSame('body-drop', $mutants[0]->mutator);
        self::assertSame("(defn f [a]\n  (+ a 1))", $mutants[0]->mutatedForm);
        self::assertSame("(defn f [a]\n  (println a))", $mutants[1]->mutatedForm);
        self::assertSame(
            '(println a) -> (removed)',
            $mutants[0]->description,
        );
    }

    public function test_a_single_body_form_is_kept(): void
    {
        self::assertSame([], $this->descriptions("(ns app.x)\n(defn f [a] (+ a 1))\n"));
    }

    public function test_the_docstring_and_the_attribute_map_are_not_body_forms(): void
    {
        $source = "(ns app.x)\n(defn f \"Doc.\" {:private true} [a] (println a) a)\n";

        self::assertSame(
            [
                '(println a) -> (removed)',
                'a -> (removed)',
            ],
            $this->descriptions($source),
        );
    }

    public function test_a_do_block_drops_each_of_its_forms(): void
    {
        $source = "(ns app.x)\n(defn f [a] (do (println a) a))\n";

        self::assertSame(
            ['(println a) -> (removed)', 'a -> (removed)'],
            $this->descriptions($source),
        );
    }

    public function test_each_arity_of_a_multi_arity_definition_drops_its_own_forms(): void
    {
        $source = "(ns app.x)\n(defn f\n  ([a] (println a) a)\n  ([a b] (+ a b)))\n";

        self::assertSame(
            ['(println a) -> (removed)', 'a -> (removed)'],
            $this->descriptions($source),
        );
    }

    public function test_an_ordinary_call_is_not_a_body(): void
    {
        self::assertSame([], $this->descriptions("(ns app.x)\n(defn f [a b] (+ a b))\n"));
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
        $generator = new MutantGenerator(new CompilerFacade(), [new BodyDropMutator()]);

        return $generator->generate('/src/x.phel', $source);
    }
}
