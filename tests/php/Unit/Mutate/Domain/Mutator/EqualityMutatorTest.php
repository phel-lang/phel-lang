<?php

declare(strict_types=1);

namespace PhelTest\Unit\Mutate\Domain\Mutator;

use Gacela\Framework\Gacela;
use Phel\Compiler\CompilerFacade;
use Phel\Mutate\Application\MutantGenerator;
use Phel\Mutate\Domain\Mutant;
use Phel\Mutate\Domain\Mutator\EqualityMutator;
use PHPUnit\Framework\TestCase;

use function array_map;

final class EqualityMutatorTest extends TestCase
{
    protected function setUp(): void
    {
        Gacela::bootstrap(__DIR__);
    }

    public function test_it_is_identified_as_equality(): void
    {
        self::assertSame('equality', new EqualityMutator()->id());
    }

    public function test_equality_and_its_negation_trade_places(): void
    {
        $source = <<<'PHEL'
        (ns app.x)
        (defn f [a b]
          (= a b)
          (not= a b))
        PHEL;

        self::assertSame(
            [
                '(= a b) -> (not= a b)',
                '(not= a b) -> (= a b)',
            ],
            $this->descriptions($source),
        );
    }

    public function test_it_reaches_a_comparison_nested_in_a_body_form(): void
    {
        $mutants = $this->generate("(ns app.x)\n(defn f [a]\n  (when (= a :ok)\n    :done))\n");

        self::assertCount(1, $mutants);
        self::assertSame('equality', $mutants[0]->mutator);
        self::assertSame("(defn f [a]\n  (when (not= a :ok)\n    :done))", $mutants[0]->mutatedForm);
    }

    public function test_other_equality_like_operators_are_left_alone(): void
    {
        $source = <<<'PHEL'
        (ns app.x)
        (defn f [a b]
          (== a b)
          (identical? a b)
          (< a b))
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
        return new MutantGenerator(new CompilerFacade(), [new EqualityMutator()])
            ->generate('/src/x.phel', $source);
    }
}
