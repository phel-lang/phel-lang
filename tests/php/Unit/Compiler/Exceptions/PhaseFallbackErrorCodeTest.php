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
        yield 'special form with too few arguments' => ['(if)', ErrorCode::INVALID_SPECIAL_FORM];
        yield 'keyword with an unknown alias' => ['::nope/foo', ErrorCode::PARSER_ERROR];
        yield 'octal escape out of range' => ['"\777"', ErrorCode::PARSER_ERROR];
        yield 'top-level reader conditional splicing' => ['#?@(:phel [1])', ErrorCode::PARSER_ERROR];
        yield 'odd-length map literal' => ['{:a}', ErrorCode::READER_ERROR];
        yield 'metadata on a value that cannot hold it' => ['^:m 1', ErrorCode::READER_ERROR];
    }

    #[DataProvider('providerUncodedErrors')]
    public function test_an_error_without_a_specific_code_carries_its_phase_code(string $source, ErrorCode $expected): void
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
