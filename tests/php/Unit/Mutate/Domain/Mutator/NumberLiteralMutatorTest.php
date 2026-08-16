<?php

declare(strict_types=1);

namespace PhelTest\Unit\Mutate\Domain\Mutator;

use Gacela\Framework\Gacela;
use Phel\Compiler\CompilerFacade;
use Phel\Mutate\Application\MutantGenerator;
use Phel\Mutate\Domain\Mutant;
use Phel\Mutate\Domain\Mutator\NumberLiteralMutator;
use PHPUnit\Framework\TestCase;

use function array_map;

final class NumberLiteralMutatorTest extends TestCase
{
    protected function setUp(): void
    {
        Gacela::bootstrap(__DIR__);
    }

    public function test_an_integer_literal_is_shifted_to_the_next_one(): void
    {
        $mutants = $this->generate("(ns app.x)\n(defn f [a] (+ a 2))\n");

        self::assertCount(1, $mutants);
        self::assertSame('literal-num', $mutants[0]->mutator);
        self::assertSame('(+ a 2) -> (+ a 3)', $mutants[0]->description);
        self::assertSame('(defn f [a] (+ a 3))', $mutants[0]->mutatedForm);
    }

    public function test_zero_becomes_one_and_one_becomes_zero(): void
    {
        $source = "(ns app.x)\n(defn f [xs] (take 1 xs))\n(defn g [xs] (take 0 xs))\n";

        self::assertSame(
            ['(take 1 xs) -> (take 0 xs)', '(take 0 xs) -> (take 1 xs)'],
            $this->descriptions($source),
        );
    }

    public function test_a_negative_literal_is_shifted_like_any_other(): void
    {
        self::assertSame(
            ['(+ a -3) -> (+ a -2)'],
            $this->descriptions("(ns app.x)\n(defn f [a] (+ a -3))\n"),
        );
    }

    public function test_floats_ratios_and_big_numbers_are_left_alone(): void
    {
        $source = "(ns app.x)\n(defn f [] [1.5 2/3 99999999999999999999])\n";

        self::assertSame([], $this->descriptions($source));
    }

    public function test_code_without_an_integer_literal_yields_no_mutants(): void
    {
        self::assertSame([], $this->descriptions("(ns app.x)\n(defn f [a] (str a))\n"));
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
        $generator = new MutantGenerator(new CompilerFacade(), [new NumberLiteralMutator()]);

        return $generator->generate('/src/x.phel', $source);
    }
}
