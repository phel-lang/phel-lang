<?php

declare(strict_types=1);

namespace PhelTest\Integration\Compiler;

use Phel\Shared\CompileOptions;
use Throwable;

use function error_reporting;
use function restore_error_handler;
use function set_error_handler;

use const E_ALL;

/**
 * PHP raises the "Return type ... should be compatible" notice as an
 * `E_DEPRECATED` while it links the class, which PHPUnit's own handler does
 * not report, so each case installs one that sees every level.
 */
final class InterfaceMethodReturnTypeRuntimeTest extends AbstractCompilerRuntimeTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->compilerFacade->eval('(ns interface-return-type)');
    }

    public function test_defstruct_interface_method_raises_no_return_type_deprecation(): void
    {
        [, $notices] = $this->evalCapturingNotices(
            '(defstruct box [n] \JsonSerializable (jsonSerialize [this] n))',
        );
        $encoded = $this->compilerFacade->eval('(php/json_encode (box 3))');

        self::assertSame([], $notices);
        self::assertSame('3', $encoded);
    }

    public function test_reify_interface_method_raises_no_return_type_deprecation(): void
    {
        [$result, $notices] = $this->evalCapturingNotices(
            '(let [n 4] (php/json_encode (reify \JsonSerializable (jsonSerialize [this] n))))',
        );

        self::assertSame('4', $result);
        self::assertSame([], $notices);
    }

    public function test_reify_void_interface_method_runs_for_effect(): void
    {
        [$result, $notices] = $this->evalCapturingNotices(
            '(let [seen (atom nil)'
            . '      obj  (reify \ArrayAccess'
            . '             (offsetExists [this k] false)'
            . '             (offsetGet [this k] nil)'
            . '             (offsetSet [this k v] (reset! seen v))'
            . '             (offsetUnset [this k] nil))]'
            . '  (php/aset obj "k" 5)'
            . '  (deref seen))',
        );

        self::assertSame(5, $result);
        self::assertSame([], $notices);
    }

    public function test_reify_header_that_is_neither_protocol_nor_interface_fails_at_compile_time(): void
    {
        try {
            $this->compilerFacade->eval('(reify NoSuchThing3412 (m [this] 1))', new CompileOptions());
            self::fail('Expected a compile-time error');
        } catch (Throwable $throwable) {
            self::assertStringContainsString('NoSuchThing3412', $throwable->getMessage());
            self::assertStringNotContainsString('syntax error', $throwable->getMessage());
        }
    }

    /**
     * @return array{0: mixed, 1: list<string>}
     */
    private function evalCapturingNotices(string $code): array
    {
        $notices = [];
        $previousLevel = error_reporting(E_ALL);
        set_error_handler(static function (int $errno, string $message) use (&$notices): bool {
            $notices[] = $message;

            return true;
        }, E_ALL);

        try {
            $result = $this->compilerFacade->eval($code, new CompileOptions());
        } finally {
            restore_error_handler();
            error_reporting($previousLevel);
        }

        return [$result, $notices];
    }
}
