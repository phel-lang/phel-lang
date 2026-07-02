<?php

declare(strict_types=1);

namespace PhelTest\Unit\Run\Application;

use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\TypeFactory;
use Phel\Run\Application\BreakpointDebugger;
use Phel\Shared\CompileOptions;
use Phel\Shared\Facade\CompilerFacadeInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function fopen;
use function fwrite;
use function rewind;
use function stream_get_contents;

final class BreakpointDebuggerTest extends TestCase
{
    public function test_eof_immediately_resumes_after_banner(): void
    {
        $output = $this->runSession('', $this->localsXY());

        self::assertStringContainsString('--- breakpoint ---', $output);
        self::assertStringContainsString('  x = 1', $output);
        self::assertStringContainsString('  y = 2', $output);
        self::assertStringContainsString('type an expression to eval it with locals in scope; (continue) to resume', $output);
        self::assertStringContainsString('break> ', $output);
        self::assertStringNotContainsString('=> ', $output);
    }

    public function test_continue_resumes(): void
    {
        $output = $this->runSession("(continue)\n", $this->localsXY());

        self::assertStringContainsString('--- breakpoint ---', $output);
        self::assertStringNotContainsString('=> ', $output);
    }

    public function test_expression_is_evaluated_with_locals_in_scope(): void
    {
        $compilerFacade = $this->createStub(CompilerFacadeInterface::class);
        $compilerFacade->method('eval')
            ->willReturnCallback(static fn(string $code, CompileOptions $options): callable => static fn(int $x, int $y): int => $x + $y);

        $output = $this->runSessionWith("(+ x y)\n", $this->localsXY(), $compilerFacade);

        self::assertStringContainsString('=> 3', $output);
    }

    public function test_error_during_eval_prints_error_and_continues_loop(): void
    {
        $compilerFacade = $this->createStub(CompilerFacadeInterface::class);
        $calls = 0;
        $compilerFacade->method('eval')
            ->willReturnCallback(static function () use (&$calls): callable {
                ++$calls;
                if ($calls === 1) {
                    throw new RuntimeException('boom');
                }

                return static fn(int $x, int $y): int => $x + $y;
            });

        $output = $this->runSessionWith("(boom)\n(+ x y)\n", $this->localsXY(), $compilerFacade);

        self::assertStringContainsString('error: boom', $output);
        self::assertStringContainsString('=> 3', $output);
    }

    public function test_locals_command_reprints_locals(): void
    {
        $output = $this->runSession(":locals\n", $this->localsXY());

        // The locals block appears once in the banner and again after :locals.
        self::assertSame(2, substr_count($output, '  x = 1'));
        self::assertSame(2, substr_count($output, '  y = 2'));
    }

    public function test_empty_map_locals_works(): void
    {
        $output = $this->runSession('', TypeFactory::getInstance()->persistentMapFromArray([]));

        self::assertStringContainsString('--- breakpoint ---', $output);
        self::assertStringContainsString('break> ', $output);
    }

    public function test_blank_line_reprompts(): void
    {
        $output = $this->runSession("\n\n", $this->localsXY());

        // Banner prompt + two re-prompts after blank lines + the EOF prompt.
        self::assertGreaterThanOrEqual(2, substr_count($output, 'break> '));
        self::assertStringNotContainsString('=> ', $output);
    }

    private function localsXY(): PersistentMapInterface
    {
        return TypeFactory::getInstance()->persistentMapFromKVs('x', 1, 'y', 2);
    }

    private function runSession(string $input, PersistentMapInterface $locals): string
    {
        return $this->runSessionWith($input, $locals, $this->createStub(CompilerFacadeInterface::class));
    }

    private function runSessionWith(string $input, PersistentMapInterface $locals, CompilerFacadeInterface $compilerFacade): string
    {
        $inputStream = fopen('php://memory', 'r+');
        self::assertNotFalse($inputStream);
        fwrite($inputStream, $input);
        rewind($inputStream);

        $outputStream = fopen('php://memory', 'r+');
        self::assertNotFalse($outputStream);

        new BreakpointDebugger($compilerFacade, $inputStream, $outputStream)->enter($locals);

        rewind($outputStream);
        $contents = stream_get_contents($outputStream);
        self::assertNotFalse($contents);

        return $contents;
    }
}
