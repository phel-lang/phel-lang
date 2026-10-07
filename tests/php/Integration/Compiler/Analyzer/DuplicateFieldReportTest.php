<?php

declare(strict_types=1);

namespace PhelTest\Integration\Compiler\Analyzer;

use Phel\Shared\CompileOptions;
use Phel\Shared\Exceptions\CompilerException;
use PhelTest\Integration\Compiler\AbstractCompilerRuntimeTestCase;
use PhelTest\Support\RendersExceptionReportTrait;
use PHPUnit\Framework\Attributes\DataProvider;

use function sprintf;

/**
 * A struct field is a constructor parameter and a key, so a repeated one has
 * no meaning; it used to reach PHP as `Redefinition of parameter $x` (#3534).
 * The caret lands on the repeated field. A field whose PHP name repeats
 * another one, or names a property the struct base class already has, is
 * rejected the same way.
 */
final class DuplicateFieldReportTest extends AbstractCompilerRuntimeTestCase
{
    use RendersExceptionReportTrait;

    private const string SOURCE = 'field.phel';

    public function test_a_defstruct_with_a_repeated_field_names_the_code(): void
    {
        $expected = <<<'REPORT'
            [PHEL007] Field x is declared more than once
            in field.phel:2

            2| (defstruct point [x y x])
                                     ^

            REPORT;

        self::assertSame($expected, $this->report("(ns field.a)\n(defstruct point [x y x])"));
    }

    public function test_a_defrecord_with_a_repeated_field_names_the_code(): void
    {
        $expected = <<<'REPORT'
            [PHEL007] Field x is declared more than once
            in field.phel:2

            2| (defrecord P [x x])
                               ^

            REPORT;

        self::assertSame($expected, $this->report("(ns field.b)\n(defrecord P [x x])"));
    }

    public function test_fields_that_munge_to_one_property_name_the_code(): void
    {
        $expected = <<<'REPORT'
            [PHEL007] Fields a-b and a_b are the same PHP property $a_b
            in field.phel:2

            2| (defstruct pair [a-b a_b])
                                    ^^^

            REPORT;

        self::assertSame($expected, $this->report("(ns field.c)\n(defstruct pair [a-b a_b])"));
    }

    /**
     * Every struct class inherits these from its base; a field with the name
     * used to stop PHP with an incompatible-type or redefinition fatal.
     */
    #[DataProvider('provideReservedFields')]
    public function test_a_field_named_like_an_inherited_property_is_reserved(string $field): void
    {
        $report = $this->report(sprintf("(ns field.r%s)\n(defstruct s [%s])", $field, $field));

        self::assertStringContainsString(
            sprintf('[PHEL007] Field %s is reserved: every struct already has a $%s property', $field, $field),
            $report,
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideReservedFields(): iterable
    {
        yield 'hasher' => ['hasher'];
        yield 'equalizer' => ['equalizer'];
        yield 'meta' => ['meta'];
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
