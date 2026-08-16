<?php

declare(strict_types=1);

namespace PhelTest\Unit\Mutate\Domain\Mutator;

use Gacela\Framework\Gacela;
use Phel\Compiler\CompilerFacade;
use Phel\Mutate\Application\MutantGenerator;
use Phel\Mutate\Domain\Mutant;
use Phel\Mutate\Domain\Mutator\BooleanLiteralMutator;
use PHPUnit\Framework\TestCase;

use function array_map;

final class BooleanLiteralMutatorTest extends TestCase
{
    protected function setUp(): void
    {
        Gacela::bootstrap(__DIR__);
    }

    public function test_it_is_identified_as_literal_bool(): void
    {
        self::assertSame('literal-bool', new BooleanLiteralMutator()->id());
    }

    public function test_each_boolean_literal_is_flipped(): void
    {
        $source = <<<'PHEL'
        (ns app.x)
        (defn f [a]
          (if true 1 2)
          (php/array-filter a true)
          (or false a))
        PHEL;

        self::assertSame(
            [
                '(if true 1 2) -> (if false 1 2)',
                '(php/array-filter a true) -> (php/array-filter a false)',
                '(or false a) -> (or true a)',
            ],
            $this->descriptions($source),
        );
    }

    public function test_a_literal_returned_straight_from_a_body_is_described_against_the_definition(): void
    {
        $mutants = $this->generate("(ns app.x)\n(defn f [] false)\n");

        self::assertCount(1, $mutants);
        self::assertSame('literal-bool', $mutants[0]->mutator);
        self::assertSame('(defn f [] false) -> (defn f [] true)', $mutants[0]->description);
        self::assertSame('(defn f [] true)', $mutants[0]->mutatedForm);
    }

    public function test_literals_that_only_look_boolean_are_left_alone(): void
    {
        $source = <<<'PHEL'
        (ns app.x)
        (defn f [a]
          (= a :true)
          (= a "false")
          (= a nil)
          (true? a))
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
        return new MutantGenerator(new CompilerFacade(), [new BooleanLiteralMutator()])
            ->generate('/src/x.phel', $source);
    }
}
