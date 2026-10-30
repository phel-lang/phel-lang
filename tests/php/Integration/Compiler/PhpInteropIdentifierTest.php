<?php

declare(strict_types=1);

namespace PhelTest\Integration\Compiler;

use Phel\Shared\CompileOptions;
use Phel\Shared\Exceptions\CompilerException;
use Phel\Shared\Exceptions\ErrorCode;
use PHPUnit\Framework\Attributes\DataProvider;

final class PhpInteropIdentifierTest extends AbstractCompilerRuntimeTestCase
{
    #[DataProvider('invalidIdentifiers')]
    public function test_invalid_identifiers_fail_before_emission(string $source): void
    {
        $this->expectException(CompilerException::class);
        $this->expectExceptionMessage('Invalid PHP identifier');
        $this->compilerFacade->compile($source, new CompileOptions()->setEmitOnly(true));
    }

    public static function invalidIdentifiers(): iterable
    {
        yield ['(.-foo-bar (new \\stdClass))'];
        yield ['(\\DateTime/foo-bar)'];
        yield ['\\DateTime/FOO-BAR'];
        yield ['\\DateTime/$foo-bar'];
        yield ['(set! \\DateTime/foo-bar 1)'];
        yield ['(set! (.-foo-bar (new \\stdClass)) 1)'];
        yield ['(instance? \\Foo-Bar nil)'];
        yield ['(new \\Vendor\\Foo-Bar)'];
        yield ['(new \\Foo\\\\Bar)'];
        yield ['(\\Foo-Bar.)'];
        yield ['(try 1 (catch \\Foo-Bar e 2))'];
        yield ['(fn [^Vendor.Foo-Bar x] x)'];
        yield ['(fn ^\\Foo-Bar [] nil)'];
        yield ['(definterface BadTagMethod (^\\Foo-Bar m [this]))'];
        yield ['(defstruct BadTagField [^\\Foo-Bar x])'];
    }

    #[DataProvider('legalIdentifiers')]
    public function test_legal_identifiers_compile_to_parseable_php(string $source, string $expected): void
    {
        $php = $this->compilerFacade->compile($source, new CompileOptions()->setEmitOnly(true))->getPhpCode();
        self::assertStringContainsString($expected, $php);
        self::assertNotEmpty(token_get_all('<?php ' . $php, TOKEN_PARSE));
    }

    public static function legalIdentifiers(): iterable
    {
        yield ['(.foo_bar (new \\stdClass))', '->foo_bar('];
        yield ['(.fooé (new \\stdClass))', '->fooé('];
        yield ['(new \\Vendor\\École)', '\\Vendor\\École('];
        yield ['(.match (new \\stdClass))', '->match('];
        yield ['(.class (new \\stdClass))', '->class('];
        yield ['(\\Unknown_Host/foo_bar)', '\\Unknown_Host::foo_bar('];
        yield ['\\Unknown_Host/$foo_bar', '\\Unknown_Host::$foo_bar'];
        yield ['(set! \\Unknown_Host/foo_bar 1)', '\\Unknown_Host::$foo_bar ='];
        yield ['(defstruct foo-bar [])', 'class foo_bar'];
        yield ['(definterface enum)', 'interface enum'];
        yield ['(definterface resource)', 'interface resource'];
        yield ['(definterface numeric)', 'interface numeric'];
        yield ['(fn [^int n] n)', 'int $n'];
        yield ['(fn [^Vendor.Foo_bar x] x)', '\\Vendor\\Foo_bar $x'];
    }

    public function test_dashed_class_tag_is_rejected(): void
    {
        $this->expectException(CompilerException::class);
        $this->expectExceptionMessage('Invalid PHP identifier Foo-Bar');
        $this->compilerFacade->compile('(fn [^\\Foo-Bar x] x)', new CompileOptions()->setEmitOnly(true));
    }

    public function test_dashed_callable_member_is_rejected(): void
    {
        $this->expectException(CompilerException::class);
        $this->expectExceptionMessage('Invalid PHP identifier foo-bar');
        $this->compilerFacade->compile('(php/callable \\DateTime foo-bar)', new CompileOptions()->setEmitOnly(true));
    }

    public function test_dashed_class_fails_at_the_class_symbol(): void
    {
        try {
            $this->compilerFacade->compile("\n(new \\Foo-Bar)", new CompileOptions()->setSource('identifier.phel')->setEmitOnly(true));
            self::fail('Invalid PHP class should fail during analysis');
        } catch (CompilerException $compilerException) {
            $error = $compilerException->getNestedException();
            self::assertSame(ErrorCode::INVALID_SPECIAL_FORM, $error->getErrorCode());
            self::assertStringContainsString('Invalid PHP identifier', $error->getMessage());
            self::assertSame(2, $error->getStartLocation()?->getLine());
            self::assertSame(5, $error->getStartLocation()?->getColumn());
        }
    }

    public function test_dashed_member_fails_at_the_member_symbol(): void
    {
        try {
            $this->compilerFacade->compile("\n(.foo-bar (new \\DateTime))", new CompileOptions()->setSource('identifier.phel'));
            self::fail('Invalid PHP member should fail during analysis');
        } catch (CompilerException $compilerException) {
            $error = $compilerException->getNestedException();
            self::assertSame(ErrorCode::INVALID_SPECIAL_FORM, $error->getErrorCode());
            self::assertStringContainsString('Invalid PHP identifier', $error->getMessage());
            self::assertSame('identifier.phel', $error->getStartLocation()?->getFile());
            self::assertSame(2, $error->getStartLocation()?->getLine());
            self::assertSame(1, $error->getStartLocation()?->getColumn());
        }
    }
}
