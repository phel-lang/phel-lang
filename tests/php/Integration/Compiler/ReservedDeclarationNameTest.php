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
        foreach (['let', 'IS', 'LeT'] as $name) {
            foreach (['(defstruct %s [])', '(defexception %s)', '(defenum %s :a)', '(definterface %s)', '(defrecord %s [])', '(deftype %s [])'] as $form) {
                yield [sprintf($form, $name), ErrorCode::INVALID_SPECIAL_FORM];
            }
        }

        foreach (['namespace', 'NaMeSpAcE', 'let', 'IS'] as $name) {
            yield ['(definterface ReservedConstants :php/const (' . $name . ' 1))', ErrorCode::INTERFACE_ERROR];
        }
    }
}
