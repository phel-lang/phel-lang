<?php

declare(strict_types=1);

namespace PhelTest\Integration\Compiler;

use Phel\Shared\CompileOptions;
use Phel\Shared\Exceptions\CompilerException;
use Phel\Shared\Exceptions\ErrorCode;
use PHPUnit\Framework\Attributes\DataProvider;

use function sprintf;

final class ReservedDeclarationNameTest extends AbstractCompilerRuntimeTestCase
{
    public function test_reserved_struct_error_points_at_the_name(): void
    {
        try {
            $this->compilerFacade->compile("\n(defstruct list [])", new CompileOptions()->setSource('reserved.phel')->setEmitOnly(true));
            self::fail('Reserved declaration should fail during analysis');
        } catch (CompilerException $compilerException) {
            $error = $compilerException->getNestedException();
            self::assertSame(ErrorCode::INVALID_SPECIAL_FORM, $error->getErrorCode());
            self::assertSame('reserved.phel', $error->getStartLocation()?->getFile());
            self::assertSame(2, $error->getStartLocation()?->getLine());
            self::assertSame(11, $error->getStartLocation()?->getColumn());
        }
    }

    #[DataProvider('reservedDeclarations')]
    public function test_reserved_declarations_fail_before_php_emission(string $source, ErrorCode $code): void
    {
        try {
            $this->compilerFacade->compile($source, new CompileOptions()->setSource('reserved.phel'))->getPhpCode();
            self::fail('Reserved declaration should fail during analysis');
        } catch (CompilerException $compilerException) {
            self::assertSame($code, $compilerException->getNestedException()->getErrorCode());
            self::assertStringContainsString('reserved PHP', $compilerException->getMessage());
        }
    }

    public static function reservedDeclarations(): iterable
    {
        foreach (['let', 'IS', 'LeT', 'list', 'match', 'class', 'never', 'void', 'mixed', 'array', 'callable', 'self', 'parent', 'static', 'fn', 'function', 'namespace', 'readonly', '__CLASS__'] as $name) {
            foreach (['(defstruct %s [])', '(defexception %s)', '(defenum %s :a)', '(definterface %s)', '(defrecord %s [])', '(deftype %s [])'] as $form) {
                yield [sprintf($form, $name), ErrorCode::INVALID_SPECIAL_FORM];
            }
        }

        foreach (['namespace', 'NaMeSpAcE', 'let', 'IS'] as $name) {
            yield ['(definterface ReservedConstants :php/const (' . $name . ' 1))', ErrorCode::INTERFACE_ERROR];
        }
    }
}
