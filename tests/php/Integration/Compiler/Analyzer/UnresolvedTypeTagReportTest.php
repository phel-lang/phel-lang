<?php

declare(strict_types=1);

namespace PhelTest\Integration\Compiler\Analyzer;

use Phel\Shared\CompileOptions;
use Phel\Shared\Exceptions\CompilerException;
use PhelTest\Integration\Compiler\AbstractCompilerRuntimeTestCase;
use PhelTest\Support\RendersExceptionReportTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * A lower-case tag that is no PHP type and no class reached the signature as
 * a class name, so the fn compiled and every call failed with
 * `must be of type strng, string given` (#3524).
 */
final class UnresolvedTypeTagReportTest extends AbstractCompilerRuntimeTestCase
{
    use RendersExceptionReportTrait;

    private const string SOURCE = 'tag.phel';

    public function test_a_misspelled_param_tag_names_the_code_at_the_tag(): void
    {
        $expected = <<<'REPORT'
            [PHEL001] Unable to resolve classname: strng. Did you mean 'string'?
            in tag.phel:2

            2| (defn f [^strng s] s)
                         ^^^^^

            REPORT;

        self::assertSame($expected, $this->report("(ns tag.a)\n(defn f [^strng s] s)"));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideRejectedTags(): iterable
    {
        yield 'clojure long param' => ['(defn f [^long n] n)', "classname: long. Did you mean 'int'?"];
        yield 'clojure double return' => ['(defn f ^double [n] n)', "classname: double. Did you mean 'float'?"];
        yield 'clojure boolean param' => ['(fn [^boolean b] b)', "classname: boolean. Did you mean 'bool'?"];
        yield 'nullable' => ['(defn f [^?long n] n)', "classname: long. Did you mean 'int'?"];
        yield 'union member' => ['(defn f [^{:tag (int strng)} n] n)', "classname: strng. Did you mean 'string'?"];
        yield 'defstruct field' => ['(defstruct p [^long x])', "classname: long. Did you mean 'int'?"];
        yield 'definterface param' => ['(definterface i (m [this ^double x]))', "classname: double. Did you mean 'float'?"];
    }

    #[Test]
    #[DataProvider('provideRejectedTags')]
    public function it_rejects_the_tag(string $form, string $message): void
    {
        self::assertStringContainsString('[PHEL001] Unable to resolve ' . $message, $this->report("(ns tag.b)\n" . $form));
    }

    public function test_builtin_alias_import_and_class_tags_still_compile(): void
    {
        $this->compilerFacade->compile(
            <<<'PHEL'
                (ns tag.c
                  (:use DateTimeImmutable :as moment))
                (defn f ^?int [^int a ^?string b ^map m ^callable c ^stdClass o ^moment d ^"int|null" e ^Not.Loaded x ^Unknown y] a)
                (defn g [^{:tag (int string)} a] a)
                PHEL,
            new CompileOptions()->setSource(self::SOURCE),
        );

        $this->expectNotToPerformAssertions();
    }

    private function report(string $phelCode): string
    {
        try {
            $this->compilerFacade->compile($phelCode, new CompileOptions()->setSource(self::SOURCE));
        } catch (CompilerException $compilerException) {
            return $this->exceptionReport(
                $compilerException->getNestedException(),
                $compilerException->getCodeSnippet(),
            );
        }

        self::fail('Expected the compiler to reject: ' . $phelCode);
    }
}
