<?php

declare(strict_types=1);

namespace PhelTest\Integration\Run\Command\Compile;

use Phel\Phel;
use Phel\Run\Infrastructure\Command\CompileCommand;
use Phel\Run\Infrastructure\PhpStdinReader;
use PhelTest\Integration\Run\Command\AbstractTestCommand;
use Symfony\Component\Console\Tester\CommandTester;

final class CompileCommandTest extends AbstractTestCommand
{
    public static function setUpBeforeClass(): void
    {
        Phel::bootstrap(__DIR__);
    }

    public function test_compile_inline_expression_emits_php(): void
    {
        $tester = new CommandTester(new CompileCommand());
        $tester->execute(['source' => '(php/+ 2 3)']);

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('(2 + 3)', $tester->getDisplay());
    }

    public function test_compile_known_core_call_uses_get_definition(): void
    {
        $tester = new CommandTester(new CompileCommand());
        $tester->execute(['source' => '(+ 1 2)']);

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString(\Phel::class . '::getDefinition("phel.core", "+"))(1, 2)', $tester->getDisplay());
    }

    public function test_compile_unbalanced_parentheses_fails(): void
    {
        $tester = new CommandTester(new CompileCommand());
        $exitCode = $tester->execute(['source' => '(broken'], ['capture_stderr_separately' => true]);

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('Unbalanced parentheses', $tester->getErrorOutput());
    }

    public function test_compile_unknown_target_fails(): void
    {
        $tester = new CommandTester(new CompileCommand());
        $exitCode = $tester->execute([
            'source' => '(+ 1 2)',
            '--target' => 'ast',
        ], ['capture_stderr_separately' => true]);

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('Unsupported target "ast"', $tester->getErrorOutput());
    }

    public function test_compile_reads_from_stdin_when_dash_argument(): void
    {
        $command = new CompileCommand($this->stdinReader('(php/* 4 5)'));
        $tester = new CommandTester($command);
        $tester->execute(['source' => '-']);

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('(4 * 5)', $tester->getDisplay());
    }

    public function test_compile_reads_from_file_path(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'phel-compile-cmd-');
        self::assertNotFalse($path);
        file_put_contents($path, '(php/- 9 4)');

        try {
            $tester = new CommandTester(new CompileCommand());
            $tester->execute(['source' => $path]);

            $tester->assertCommandIsSuccessful();
            self::assertStringContainsString('(9 - 4)', $tester->getDisplay());
        } finally {
            @unlink($path);
        }
    }

    public function test_compile_empty_source_succeeds_with_no_output(): void
    {
        $tester = new CommandTester(new CompileCommand());
        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        self::assertSame('', $tester->getDisplay());
    }

    private function stdinReader(string $contents): PhpStdinReader
    {
        $stream = fopen('php://memory', 'r+');
        self::assertIsResource($stream);
        fwrite($stream, $contents);
        rewind($stream);

        return new PhpStdinReader($stream);
    }
}
