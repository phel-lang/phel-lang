<?php

declare(strict_types=1);

namespace PhelTest\Unit\Mutate\Domain\Mutator;

use Gacela\Framework\Gacela;
use Phel\Compiler\CompilerFacade;
use Phel\Mutate\Application\MutantGenerator;
use Phel\Mutate\Domain\Mutant;
use Phel\Mutate\Domain\Mutator\LogicMutator;
use PHPUnit\Framework\TestCase;

use function array_map;

final class LogicMutatorTest extends TestCase
{
    protected function setUp(): void
    {
        Gacela::bootstrap(__DIR__);
    }

    public function test_it_is_identified_as_logic(): void
    {
        self::assertSame('logic', new LogicMutator()->id());
    }

    public function test_and_and_or_trade_places(): void
    {
        $source = <<<'PHEL'
        (ns app.x)
        (defn f [a b]
          (and a b)
          (or a b))
        PHEL;

        self::assertSame(
            [
                '(and a b) -> (or a b)',
                '(or a b) -> (and a b)',
            ],
            $this->descriptions($source),
        );
    }

    public function test_a_not_wrapper_is_dropped_and_described_by_the_child_alone(): void
    {
        $mutants = $this->generate("(ns app.x)\n(defn f [a]\n  (if (not a)\n    :yes\n    :no))\n");

        self::assertCount(1, $mutants);
        self::assertSame('logic', $mutants[0]->mutator);
        self::assertSame('(not a) -> a', $mutants[0]->description);
        self::assertSame("(defn f [a]\n  (if a\n    :yes\n    :no))", $mutants[0]->mutatedForm);
    }

    public function test_the_dropped_wrapper_leaves_a_compound_operand_intact(): void
    {
        $mutants = $this->generate("(ns app.x)\n(defn f [a b] (not (= a b)))\n");

        self::assertSame(['(not (= a b)) -> (= a b)'], array_map(static fn(Mutant $m): string => $m->description, $mutants));
        self::assertSame('(defn f [a b] (= a b))', $mutants[0]->mutatedForm);
    }

    public function test_a_not_with_anything_but_one_operand_is_not_a_wrapper_it_can_undo(): void
    {
        $source = <<<'PHEL'
        (ns app.x)
        (defn f [a b]
          (not a b)
          (not)
          (nor a b)
          [not a])
        PHEL;

        self::assertSame([], $this->descriptions($source));
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
        return new MutantGenerator(new CompilerFacade(), [new LogicMutator()])
            ->generate('/src/x.phel', $source);
    }
}
