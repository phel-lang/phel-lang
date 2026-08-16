<?php

declare(strict_types=1);

namespace PhelTest\Unit\Mutate\Domain\Mutator;

use Gacela\Framework\Gacela;
use Phel\Compiler\CompilerFacade;
use Phel\Mutate\Application\MutantGenerator;
use Phel\Mutate\Domain\Mutant;
use Phel\Mutate\Domain\Mutator\ComparisonMutator;
use PHPUnit\Framework\TestCase;

use function array_map;

final class ComparisonMutatorTest extends TestCase
{
    protected function setUp(): void
    {
        Gacela::bootstrap(__DIR__);
    }

    public function test_it_is_identified_as_compare(): void
    {
        self::assertSame('compare', new ComparisonMutator()->id());
    }

    public function test_each_comparison_moves_across_its_boundary(): void
    {
        $source = <<<'PHEL'
        (ns app.x)
        (defn f [a b]
          (< a b)
          (<= a b)
          (> a b)
          (>= a b))
        PHEL;

        self::assertSame(
            [
                '(< a b) -> (<= a b)',
                '(<= a b) -> (< a b)',
                '(> a b) -> (>= a b)',
                '(>= a b) -> (> a b)',
            ],
            $this->descriptions($source),
        );
    }

    public function test_the_rest_of_the_form_is_re_emitted_unchanged(): void
    {
        $mutants = $this->generate("(ns app.x)\n(defn f [a b]\n  (if (< a b)\n    :less\n    :more))\n");

        self::assertCount(1, $mutants);
        self::assertSame('compare', $mutants[0]->mutator);
        self::assertSame("(defn f [a b]\n  (if (<= a b)\n    :less\n    :more))", $mutants[0]->mutatedForm);
    }

    public function test_operators_outside_the_boundary_family_are_left_alone(): void
    {
        $source = <<<'PHEL'
        (ns app.x)
        (defn f [a b]
          (= a b)
          (+ a b)
          (compare a b))
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
        return new MutantGenerator(new CompilerFacade(), [new ComparisonMutator()])
            ->generate('/src/x.phel', $source);
    }
}
