<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Exceptions;

use Phel;
use Phel\Compiler\CompilerFacade;
use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;
use Phel\Lang\TypeInterface;
use Phel\Shared\Exceptions\AbstractLocatedException;
use Phel\Shared\Exceptions\ErrorCode;
use Phel\Shared\Parser\Node\NodeInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A compile error with no more specific code carries its phase's fallback
 * code, so `phel run` prints the same `[PHELxxx]` that `phel analyze` reports.
 */
final class PhaseFallbackErrorCodeTest extends TestCase
{
    protected function setUp(): void
    {
        Phel::bootstrap(__DIR__);
        GlobalEnvironmentSingleton::initializeNew();
    }

    public static function providerUncodedErrors(): iterable
    {
        yield 'form after finally' => ['(try 1 (finally 2) 3)', ErrorCode::INVALID_SPECIAL_FORM];
        yield 'keyword with an unknown alias' => ['::nope/foo', ErrorCode::PARSER_ERROR];
        yield 'octal escape out of range' => ['"\777"', ErrorCode::PARSER_ERROR];
        yield 'top-level reader conditional splicing' => ['#?@(:phel [1])', ErrorCode::PARSER_ERROR];
        yield 'odd-length map literal' => ['{:a}', ErrorCode::READER_ERROR];
        yield 'metadata on a value that cannot hold it' => ['^:m 1', ErrorCode::READER_ERROR];
    }

    public static function providerSpecialFormErrors(): iterable
    {
        yield 'special form with too few arguments' => ['(if)', ErrorCode::ARITY_ERROR];
        yield 'special form with too many arguments' => ['(in-ns a b)', ErrorCode::ARITY_ERROR];
        yield 'wrong kind of value in a fixed position' => ['(fn 1)', ErrorCode::TYPE_ERROR];
        yield 'literal tail contradicting the return tag' => ['(fn ^int [] "x")', ErrorCode::TYPE_ERROR];
        yield 'qualified fn parameter' => ['(fn [a/b] 1)', ErrorCode::BINDING_ERROR];
        yield 'var with no global definition' => ['(var nope)', ErrorCode::UNDEFINED_SYMBOL];
        yield 'catch type that resolves to nothing' => ['(try 1 (catch nope e 2))', ErrorCode::UNDEFINED_SYMBOL];
    }

    #[DataProvider('providerUncodedErrors')]
    public function test_an_error_without_a_specific_code_carries_its_phase_code(string $source, ErrorCode $expected): void
    {
        self::assertSame($expected, $this->errorCodeOf($source));
    }

    #[DataProvider('providerSpecialFormErrors')]
    public function test_a_special_form_error_carries_the_code_of_its_mistake(string $source, ErrorCode $expected): void
    {
        self::assertSame($expected, $this->errorCodeOf($source));
    }

    private function errorCodeOf(string $source): ?ErrorCode
    {
        $facade = new CompilerFacade();

        try {
            $node = $facade->parseNext($facade->lexString($source));
            self::assertInstanceOf(NodeInterface::class, $node);
            /** @var bool|float|int|string|TypeInterface|null $ast */
            $ast = $facade->read($node)->getAst();
            $facade->analyze($ast, $facade->emptyNodeEnvironment()->withReturnContext());
        } catch (AbstractLocatedException $locatedException) {
            return $locatedException->getErrorCode();
        }

        self::fail('Expected a compile error for: ' . $source);
    }
}
