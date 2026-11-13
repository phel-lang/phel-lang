<?php

declare(strict_types=1);

namespace PhelTest\Integration\Compiler;

use Phel\Shared\CompileOptions;
use Phel\Shared\Exceptions\CompilerException;
use Phel\Shared\Exceptions\ErrorCode;
use PHPUnit\Framework\Attributes\DataProvider;

use function sprintf;

final class DeclarationNameTypeTest extends AbstractCompilerRuntimeTestCase
{
    #[DataProvider('nonSymbolDeclarations')]
    public function test_declaration_requires_a_symbol_name(string $form): void
    {
        try {
            $this->compilerFacade->compile("\n" . $form, new CompileOptions()->setSource('declarations.phel'));
            self::fail('Expected a declaration name error');
        } catch (CompilerException $compilerException) {
            $error = $compilerException->getNestedException();
            self::assertSame(ErrorCode::TYPE_ERROR, $error->getErrorCode());
            self::assertSame('declarations.phel', $error->getStartLocation()?->getFile());
            self::assertSame(2, $error->getStartLocation()?->getLine());
            self::assertStringNotContainsString('getName()', $compilerException->getMessage());
            self::assertStringNotContainsString('PHEL005', $compilerException->getMessage());
        }
    }

    public static function nonSymbolDeclarations(): iterable
    {
        foreach (['42', 'nil', '"bad"', ':bad'] as $name) {
            foreach (['(defstruct %s [x])', '(definterface %s (m [this]))', '(defexception %s)', '(defexception %s RuntimeException)', '(defenum %s :case)', '(defrecord %s [x])', '(deftype %s [x])', '(defprotocol %s (m [this]))'] as $form) {
                yield $name . ' ' . $form => [sprintf($form, $name)];
            }
        }
    }
}
