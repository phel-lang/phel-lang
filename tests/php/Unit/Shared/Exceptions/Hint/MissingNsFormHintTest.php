<?php

declare(strict_types=1);

namespace PhelTest\Unit\Shared\Exceptions\Hint;

use Error;
use Phel\Shared\Exceptions\Hint\ExceptionHintResolver;
use Phel\Shared\Exceptions\Hint\MissingNsFormHint;
use Phel\Shared\Exceptions\Hint\UndefinedSymbolHint;
use Phel\Shared\Exceptions\MissingNsFormException;
use PHPUnit\Framework\TestCase;

final class MissingNsFormHintTest extends TestCase
{
    public function test_names_the_file_and_the_missing_ns_form(): void
    {
        $hint = new MissingNsFormHint();
        $e = MissingNsFormException::inFile('src/app.phel', new Error("Cannot resolve symbol 'defn-'"));

        self::assertTrue($hint->appliesTo($e));
        self::assertSame(
            "'src/app.phel' does not start with an (ns ...) form. Make (ns ...) the first form of the file.",
            $hint->hint($e),
        );
    }

    public function test_does_not_apply_to_an_unresolved_symbol_in_a_file_with_ns(): void
    {
        self::assertFalse(new MissingNsFormHint()->appliesTo(new Error("Cannot resolve symbol 'defn-'")));
    }

    public function test_wins_over_the_undefined_symbol_hint_it_wraps(): void
    {
        $resolver = new ExceptionHintResolver([new MissingNsFormHint(), new UndefinedSymbolHint()]);
        $e = MissingNsFormException::inFile('src/app.phel', new Error("Cannot resolve symbol 'defn-'"));

        self::assertStringContainsString('(ns ...)', (string) $resolver->hintFor($e));
    }

    public function test_keeps_the_analyzer_error_as_the_cause(): void
    {
        $cause = new Error("Cannot resolve symbol 'defn-'");
        $e = MissingNsFormException::inFile('src/app.phel', $cause);

        self::assertSame($cause, $e->getPrevious());
        self::assertStringContainsString("Cannot resolve symbol 'defn-'", $e->getMessage());
    }
}
