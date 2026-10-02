<?php

declare(strict_types=1);

namespace PhelTest\Unit\Shared\Exceptions\Hint;

use Error;
use Phel\Shared\Exceptions\Hint\ClassNotFoundHint;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function sprintf;

final class ClassNotFoundHintTest extends TestCase
{
    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function javaClassProvider(): iterable
    {
        yield 'Integer/parseInt' => ['Integer', '(parse-long s)'];
        yield 'Math/abs' => ['Math', '(abs x)'];
        yield 'System/currentTimeMillis' => ['System', '(php/microtime true)'];
        yield 'Thread/sleep' => ['Thread', '(php/usleep (* ms 1000))'];
        yield 'String/valueOf' => ['String', '(str x)'];
    }

    #[DataProvider('javaClassProvider')]
    public function test_names_the_phel_replacement_of_a_java_class(string $class, string $replacement): void
    {
        $e = new Error(sprintf('Class "%s" not found', $class));
        $hint = new ClassNotFoundHint();

        self::assertTrue($hint->appliesTo($e));
        self::assertStringContainsString($class . ' is a Java class', $hint->hint($e));
        self::assertStringContainsString($replacement, $hint->hint($e));
    }

    public function test_a_method_call_on_a_string_points_at_phel_string(): void
    {
        $hint = new ClassNotFoundHint()->hint(new Error('Class "abc" not found'));

        self::assertStringContainsString('called on a string', $hint);
        self::assertStringContainsString('phel.string', $hint);
    }

    public function test_a_string_that_is_no_class_name_points_at_phel_string(): void
    {
        self::assertStringContainsString('phel.string', new ClassNotFoundHint()->hint(new Error('Class "hello world" not found')));
    }

    public function test_an_unknown_class_points_at_its_use_and_autoloading(): void
    {
        $hint = new ClassNotFoundHint()->hint(new Error('Class "App\\Missing" not found'));

        self::assertStringContainsString("class 'App\\Missing' is not loaded", $hint);
        self::assertStringContainsString('Composer', $hint);
    }

    public function test_does_not_apply_to_other_errors(): void
    {
        self::assertFalse(new ClassNotFoundHint()->appliesTo(new Error('Call to undefined function foo()')));
    }
}
