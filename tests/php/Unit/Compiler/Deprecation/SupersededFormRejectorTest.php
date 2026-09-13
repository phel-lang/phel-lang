<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Deprecation;

use Phel;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Domain\Deprecation\SupersededFormRejector;
use Phel\Lang\Symbol;
use Phel\Shared\Exceptions\ErrorCode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function sprintf;

final class SupersededFormRejectorTest extends TestCase
{
    /**
     * @return iterable<string, array{0: string}>
     */
    public static function supersededFormProvider(): iterable
    {
        yield 'php/new' => [Symbol::NAME_PHP_NEW];
        yield 'php/->' => [Symbol::NAME_PHP_OBJECT_CALL];
        yield 'php/::' => [Symbol::NAME_PHP_OBJECT_STATIC_CALL];
        yield 'set-var' => [Symbol::NAME_SET_VAR];
    }

    #[DataProvider('supersededFormProvider')]
    public function test_every_superseded_head_is_rejected(string $name): void
    {
        $this->expectException(AnalyzerException::class);
        $this->expectExceptionMessage(sprintf('"%s" is no longer valid source for', $name));

        new SupersededFormRejector()->rejectIfWritten(
            Phel::list([Symbol::create($name), Symbol::create('x')]),
        );
    }

    public function test_the_rejection_carries_the_superseded_form_code(): void
    {
        try {
            new SupersededFormRejector()->rejectIfWritten(
                Phel::list([Symbol::create(Symbol::NAME_PHP_NEW)]),
            );
        } catch (AnalyzerException $analyzerException) {
            self::assertSame(ErrorCode::SUPERSEDED_FORM, $analyzerException->getErrorCode());

            return;
        }

        self::fail('The form was not rejected.');
    }

    /**
     * The reader hands over a whole top-level form, so the check has to reach
     * a superseded head wherever it sits inside one.
     */
    public function test_a_nested_head_is_rejected(): void
    {
        $this->expectException(AnalyzerException::class);

        new SupersededFormRejector()->rejectIfWritten(
            Phel::list([
                Symbol::create(Symbol::NAME_DO),
                Phel::vector([
                    Phel::map(
                        Symbol::create('k'),
                        Phel::list([Symbol::create(Symbol::NAME_PHP_NEW), Symbol::create('x')]),
                    ),
                ]),
            ]),
        );
    }

    /**
     * `'(php/new \Foo)` is data. A quasiquote is already lowered by the time
     * this runs, so a macro template reads as `(apply list (concat …))` and
     * keeps the name as a quoted symbol rather than a head.
     */
    public function test_a_quoted_subtree_is_left_alone(): void
    {
        $this->expectNotToPerformAssertions();

        new SupersededFormRejector()->rejectIfWritten(
            Phel::list([
                Symbol::create(Symbol::NAME_QUOTE),
                Phel::list([Symbol::create(Symbol::NAME_PHP_NEW), Symbol::create('x')]),
            ]),
        );
    }

    public function test_a_form_phel_still_spells_this_way_is_left_alone(): void
    {
        $this->expectNotToPerformAssertions();

        new SupersededFormRejector()->rejectIfWritten(
            Phel::list([Symbol::create(Symbol::NAME_PHP_OBJECT_SET), Symbol::create('x')]),
        );
    }

    public function test_the_published_names_are_the_forms_it_rejects(): void
    {
        self::assertSame(
            [
                Symbol::NAME_PHP_NEW,
                Symbol::NAME_PHP_OBJECT_CALL,
                Symbol::NAME_PHP_OBJECT_STATIC_CALL,
                Symbol::NAME_SET_VAR,
            ],
            SupersededFormRejector::supersededFormNames(),
        );
    }
}
