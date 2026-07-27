<?php

declare(strict_types=1);

namespace PhelTest\Integration\Compiler;

use Phel\Compiler\Domain\Evaluator\Exceptions\EvaluatedCodeException;
use Phel\Lang\Keyword;
use Phel\Shared\CompileOptions;

final class PhpInvokeRuntimeTest extends AbstractCompilerRuntimeTestCase
{
    public function test_it_calls_a_method_whose_name_is_a_runtime_value(): void
    {
        $result = $this->compilerFacade->eval(
            '(let [m "format"] (php-invoke (php/new \\DateTimeImmutable "2024-03-10") m "Y"))',
            new CompileOptions(),
        );

        self::assertSame('2024', $result);
    }

    public function test_it_calls_a_static_method_through_a_class_name_string(): void
    {
        $result = $this->compilerFacade->eval(
            '(php-invoke "\\\\Phel\\\\Lang\\\\Keyword" "create" "foo")',
            new CompileOptions(),
        );

        self::assertInstanceOf(Keyword::class, $result);
        self::assertSame('foo', $result->getName());
    }

    public function test_an_undefined_method_reports_the_same_wording_as_a_literal_call(): void
    {
        $this->expectException(EvaluatedCodeException::class);
        $this->expectExceptionMessage('Call to undefined method DateTimeImmutable::nope()');

        $this->compilerFacade->eval(
            '(php-invoke (php/new \\DateTimeImmutable "2024-03-10") "nope")',
            new CompileOptions(),
        );
    }
}
