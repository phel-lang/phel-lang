<?php

declare(strict_types=1);

namespace PhelTest\Unit\Shared\Exceptions\Hint;

use Phel;
use Phel\Shared\Exceptions\Hint\ExceptionHintResolver;
use Phel\Shared\Exceptions\Hint\PhpArrayArgumentHint;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;
use TypeError;

final class PhpArrayArgumentHintTest extends TestCase
{
    public static function collections(): iterable
    {
        yield 'map' => [Phel::map('x', 1), 'map'];
        yield 'vector' => [Phel::vector([1, 2]), 'vector'];
    }

    #[DataProvider('collections')]
    public function test_real_array_argument_error_keeps_its_cause_and_message(mixed $value, string $kind): void
    {
        try {
            array_keys($value);
            self::fail('The PHP call must reject a Phel collection.');
        } catch (TypeError $typeError) {
            $message = $typeError->getMessage();
            $wrapped = new RuntimeException('wrapper', previous: $typeError);
            $resolver = new ExceptionHintResolver([new PhpArrayArgumentHint()]);
            $hint = $resolver->hintFor($wrapped);
            self::assertNotNull($hint);
            self::assertStringContainsString('received a Phel ' . $kind, $hint);
            self::assertStringContainsString('(to-array value)', $hint);
            self::assertStringContainsString('(phel->php value)', $hint);
            self::assertSame($message, $typeError->getMessage());
            self::assertSame($typeError, $wrapped->getPrevious());
        }
    }

    public static function unrelatedErrors(): iterable
    {
        yield 'scalar argument' => [new TypeError('f(): Argument #1 ($x) must be of type array, string given')];
        yield 'wrong expected type' => [new TypeError('f(): Argument #1 ($x) must be of type int, Phel\\Lang\\Collections\\Vector\\PersistentVector given')];
        yield 'wrong exception class' => [new RuntimeException('f(): Argument #1 ($x) must be of type array, Phel\\Lang\\Collections\\Vector\\PersistentVector given')];
        yield 'unloaded class' => [new TypeError('f(): Argument #1 ($x) must be of type array, UnloadedCollection given')];
        yield 'return type' => [new TypeError('f(): Return value must be of type array, Phel\\Lang\\Collections\\Vector\\PersistentVector returned')];
    }

    #[DataProvider('unrelatedErrors')]
    public function test_other_errors_do_not_get_an_array_conversion_hint(Throwable $error): void
    {
        self::assertFalse(new PhpArrayArgumentHint()->appliesTo($error));
    }
}
