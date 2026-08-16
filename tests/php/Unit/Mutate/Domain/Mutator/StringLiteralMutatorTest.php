<?php

declare(strict_types=1);

namespace PhelTest\Unit\Mutate\Domain\Mutator;

use Gacela\Framework\Gacela;
use Phel\Compiler\CompilerFacade;
use Phel\Mutate\Application\MutantGenerator;
use Phel\Mutate\Domain\Mutant;
use Phel\Mutate\Domain\Mutator\StringLiteralMutator;
use PHPUnit\Framework\TestCase;

use function array_map;

final class StringLiteralMutatorTest extends TestCase
{
    protected function setUp(): void
    {
        Gacela::bootstrap(__DIR__);
    }

    public function test_every_non_empty_string_literal_is_emptied(): void
    {
        $mutants = $this->generate("(ns app.x)\n(defn f [a] (str \"n = \" a))\n");

        self::assertCount(1, $mutants);
        self::assertSame('literal-str', $mutants[0]->mutator);
        self::assertSame('(str "n = " a) -> (str "" a)', $mutants[0]->description);
        self::assertSame('(defn f [a] (str "" a))', $mutants[0]->mutatedForm);
    }

    public function test_each_literal_of_a_form_is_a_separate_mutant(): void
    {
        self::assertSame(
            ['(str "a" "b") -> (str "" "b")', '(str "a" "b") -> (str "a" "")'],
            $this->descriptions("(ns app.x)\n(defn f [] (str \"a\" \"b\"))\n"),
        );
    }

    public function test_an_empty_string_is_left_alone(): void
    {
        self::assertSame(
            ['(str "" "b") -> (str "" "")'],
            $this->descriptions("(ns app.x)\n(defn f [] (str \"\" \"b\"))\n"),
        );
    }

    public function test_the_docstring_is_not_a_literal_of_the_body(): void
    {
        $source = "(ns app.x)\n(defn f \"Doc.\" [] \"body\")\n";

        self::assertSame(
            ['(defn f "Doc." [] "body") -> (defn f "Doc." [] "")'],
            $this->descriptions($source),
        );
    }

    public function test_code_without_a_string_literal_yields_no_mutants(): void
    {
        self::assertSame([], $this->descriptions("(ns app.x)\n(defn f [a] (+ a 1))\n"));
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
        $generator = new MutantGenerator(new CompilerFacade(), [new StringLiteralMutator()]);

        return $generator->generate('/src/x.phel', $source);
    }
}
