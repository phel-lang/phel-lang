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
        $this->compileEmitOnly($source);
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
        $php = $this->compileEmitOnly($source);
        self::assertStringContainsString($expected, $php);
        self::assertNotEmpty(token_get_all('<?php ' . $php, TOKEN_PARSE));
    }

    public static function legalIdentifiers(): iterable
    {
        yield ['(php/strlen "x")', 'strlen("x")'];
        yield ['php/PHP_EOL', 'PHP_EOL'];
        yield ['php/$_SERVER', '$_SERVER'];
        yield ['php/$GLOBALS', '$GLOBALS'];
        yield ['(php/! false)', '!(false)'];
        yield ['(fn [n] (php/~ n))', '~($n)'];
        yield ['(php/+ 1 2)', '(1 + 2)'];
        yield ['(php/Vendor.function_name "x")', '\\Vendor\\function_name("x")'];
        yield ['(php/Vendor/function_name "x")', '\\Vendor\\function_name("x")'];
        yield ['(php/Vendor\\function_name "x")', '\\Vendor\\function_name("x")'];
        yield ['(php/\\strlen "x")', '\\strlen("x")'];
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

    #[DataProvider('invalidPhpReferences')]
    public function test_invalid_php_references_fail_at_the_symbol(string $source, string $name, int $column): void
    {
        try {
            $this->compilerFacade->compile("\n" . $source, new CompileOptions()->setSource('php-reference.phel')->setEmitOnly(true));
            self::fail('Invalid PHP reference should fail during analysis');
        } catch (CompilerException $compilerException) {
            $error = $compilerException->getNestedException();
            self::assertSame(ErrorCode::INVALID_SPECIAL_FORM, $error->getErrorCode());
            self::assertStringContainsString('Invalid PHP identifier ' . $name, $error->getMessage());
            self::assertSame('php-reference.phel', $error->getStartLocation()?->getFile());
            self::assertSame(2, $error->getStartLocation()?->getLine());
            self::assertSame($column, $error->getStartLocation()?->getColumn());
        }
    }

    public static function invalidPhpReferences(): iterable
    {
        yield ['(php/strlen;phpinfo "x")', 'strlen;phpinfo', 1];
        yield ['(def x php/PHP_EOL;phpinfo)', 'PHP_EOL;phpinfo', 7];
        yield ['(defn f [x] (php/$GLOBALS;x x))', 'GLOBALS;x', 13];
        yield ['(def x php/E_ALL|E_STRICT)', 'E_ALL|E_STRICT', 7];
    }

    #[DataProvider('classMemberReferences')]
    public function test_a_php_class_member_reference_names_the_supported_spelling(string $source, string $message): void
    {
        try {
            $this->compilerFacade->compile($source, new CompileOptions()->setEmitOnly(true));
            self::fail('A php/Class::member reference should fail during analysis');
        } catch (CompilerException $compilerException) {
            $error = $compilerException->getNestedException();
            self::assertSame(ErrorCode::INVALID_SPECIAL_FORM, $error->getErrorCode());
            self::assertStringContainsString($message, $error->getMessage());
            self::assertStringContainsString('docs/migration/upgrade-0.49-to-1.0.md', $error->getMessage());
        }
    }

    public static function classMemberReferences(): iterable
    {
        yield 'constant' => ['php/PDO::ATTR_ERRMODE', 'Write a class member as PDO/ATTR_ERRMODE, not php/PDO::ATTR_ERRMODE.'];
        yield 'static method call' => ['(php/DateTime::createFromFormat "U" "0")', 'Write a class member as DateTime/createFromFormat,'];
        yield 'static property' => ['php/Foo::$bar', 'Write a class member as Foo/$bar,'];
        yield 'global class' => ['php/\\Unknown_Host::BAR', 'Write a class member as Unknown_Host/BAR,'];
        yield 'namespaced class' => ['php/Vendor\\Foo::BAR', 'Write a class member as Vendor.Foo/BAR,'];
        yield 'dotted class' => ['php/Vendor.Foo::BAR', 'Write a class member as Vendor.Foo/BAR,'];
        yield 'lower-case class' => ['php/stdClass::FOO', 'Write a class member as \\stdClass/FOO,'];
        yield 'slash-separated class' => ['php/Vendor/Foo::BAR', 'Write a class member as Vendor.Foo/BAR,'];
    }

    #[DataProvider('suggestedSpellings')]
    public function test_the_suggested_spelling_compiles(string $source, string $expected): void
    {
        self::assertStringContainsString($expected, $this->compileEmitOnly($source));
    }

    public static function suggestedSpellings(): iterable
    {
        yield ['Unknown_Host/ATTR_ERRMODE', '\\Unknown_Host::ATTR_ERRMODE'];
        yield ['Vendor.Foo/BAR', '\\Vendor\\Foo::BAR'];
        yield ['Vendor.Foo/$bar', '\\Vendor\\Foo::$bar'];
        yield ['\\unknown_host/FOO', '\\unknown_host::FOO'];
    }

    #[DataProvider('nonMemberDoubleColons')]
    public function test_a_double_colon_that_names_no_class_member_keeps_the_identifier_message(string $source): void
    {
        $this->expectException(CompilerException::class);
        $this->expectExceptionMessageMatches('/Invalid PHP identifier .*\\. Use letters/');
        $this->compilerFacade->compile($source, new CompileOptions()->setEmitOnly(true));
    }

    public static function nonMemberDoubleColons(): iterable
    {
        yield ['php/::'];
        yield ['php/Foo::'];
        yield ['php/::BAR'];
        yield ['php/Foo::bar-baz'];
        yield ['php/self::BAR'];
        yield ['php/PDO::class::ATTR_ERRMODE'];
        yield ['php/$class::ATTR_ERRMODE'];
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

    /**
     * A few cases spell a PHP namespace with `\` on purpose: the dot form is
     * not read as a class there. Their backslash notice is expected; any
     * other deprecation still fails the test.
     */
    private function compileEmitOnly(string $source): string
    {
        $unexpected = [];
        $previousHandler = null;
        $previousHandler = set_error_handler(
            static function (int $level, string $message, string $file, int $line) use (&$unexpected, &$previousHandler): bool {
                if ($level !== E_USER_DEPRECATED) {
                    return $previousHandler !== null && $previousHandler($level, $message, $file, $line) !== false;
                }

                if (!str_starts_with($message, "Backslash ('\\') namespace separator")) {
                    $unexpected[] = $message;
                }

                return true;
            },
        );

        try {
            return $this->compilerFacade->compile($source, new CompileOptions()->setEmitOnly(true))->getPhpCode();
        } finally {
            restore_error_handler();
            self::assertSame([], $unexpected);
        }
    }
}
