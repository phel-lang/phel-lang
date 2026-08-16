<?php

declare(strict_types=1);

namespace PhelTest\Unit\Mutate\Domain\Mutator;

use Gacela\Framework\Gacela;
use Phel\Compiler\CompilerFacade;
use Phel\Mutate\Application\MutantGenerator;
use Phel\Mutate\Domain\Mutant;
use Phel\Mutate\Domain\Mutator\SequenceMutator;
use PHPUnit\Framework\TestCase;

use function array_map;

final class SequenceMutatorTest extends TestCase
{
    protected function setUp(): void
    {
        Gacela::bootstrap(__DIR__);
    }

    public function test_every_pair_is_swapped_in_both_directions(): void
    {
        $source = <<<'PHEL'
        (ns app.x)
        (defn f [xs n]
          (first xs)
          (last xs)
          (inc n)
          (dec n)
          (conj xs n)
          (disj xs n)
          (take n xs)
          (drop n xs)
          (min n 1)
          (max n 1))
        PHEL;

        self::assertSame(
            [
                '(first xs) -> (last xs)',
                '(last xs) -> (first xs)',
                '(inc n) -> (dec n)',
                '(dec n) -> (inc n)',
                '(conj xs n) -> (disj xs n)',
                '(disj xs n) -> (conj xs n)',
                '(take n xs) -> (drop n xs)',
                '(drop n xs) -> (take n xs)',
                '(min n 1) -> (max n 1)',
                '(max n 1) -> (min n 1)',
            ],
            $this->descriptions($source),
        );
    }

    public function test_the_mutator_is_reported_under_its_id(): void
    {
        $mutants = $this->generate("(ns app.x)\n(defn f [xs] (first xs))\n");

        self::assertCount(1, $mutants);
        self::assertSame('seq-op', $mutants[0]->mutator);
        self::assertSame('(defn f [xs] (last xs))', $mutants[0]->mutatedForm);
    }

    public function test_an_operation_passed_as_a_value_is_swapped_too(): void
    {
        self::assertSame(
            ['(map inc xs) -> (map dec xs)'],
            $this->descriptions("(ns app.x)\n(defn f [xs] (map inc xs))\n"),
        );
    }

    public function test_a_qualified_or_unrelated_symbol_is_left_alone(): void
    {
        $source = "(ns app.x)\n(defn f [xs] (phel.core/first xs) (second xs))\n";

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
        $generator = new MutantGenerator(new CompilerFacade(), [new SequenceMutator()]);

        return $generator->generate('/src/x.phel', $source);
    }
}
