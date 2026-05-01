<?php

declare(strict_types=1);

namespace PhelTest\Integration\Run\Command\Eval;

use Phel\Phel;
use Phel\Run\Infrastructure\Command\EvalCommand;
use Phel\Run\Infrastructure\PhpStdinReader;
use PhelTest\Integration\Run\Command\AbstractTestCommand;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Console\Input\InputInterface;

final class EvalCommandTest extends AbstractTestCommand
{
    public static function setUpBeforeClass(): void
    {
        Phel::bootstrap(__DIR__);
    }

    public function test_eval_success(): void
    {
        $this->expectOutputRegex('/5/');

        $this->createEvalCommand()->run(
            $this->stubInput('(php/+ 2 3)'),
            $this->stubOutput(),
        );
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function bundledNamespaceFqnProvider(): iterable
    {
        yield 'phel.async/delay'        => ['phel.async/delay',        '/<function:delay>/'];
        yield 'phel.html/escape-html'   => ['phel.html/escape-html',   '/<function:escape-html>/'];
        yield 'phel.json/encode'        => ['phel.json/encode',        '/<function:encode>/'];
    }

    #[DataProvider('bundledNamespaceFqnProvider')]
    public function test_eval_resolves_bundled_namespace_fqn_without_explicit_require(
        string $expression,
        string $expectedOutputRegex,
    ): void {
        $this->expectOutputRegex($expectedOutputRegex);

        $exitCode = $this->createEvalCommand()->run(
            $this->stubInput($expression),
            $this->stubOutput(),
        );

        self::assertSame(0, $exitCode);
    }

    public function test_eval_resolves_bare_stdclass(): void
    {
        $this->expectOutputRegex('/Printer cannot print this type: .*stdClass/');

        $exitCode = $this->createEvalCommand()->run(
            $this->stubInput('(new stdClass)'),
            $this->stubOutput(),
        );

        self::assertSame(0, $exitCode);
    }

    public function test_eval_empty_expression(): void
    {
        $exitCode = $this->createEvalCommand()->run(
            $this->stubInput(''),
            $this->stubOutput(),
        );

        self::assertSame(0, $exitCode);
    }

    public function test_eval_failure_unbalanced_parentheses(): void
    {
        $this->expectOutputRegex('/Unbalanced parentheses/');

        $exitCode = $this->createEvalCommand()->run(
            $this->stubInput('(invalid'),
            $this->stubOutput(),
        );

        self::assertSame(1, $exitCode);
    }

    public function test_eval_reads_from_stdin_when_dash_argument(): void
    {
        $command = new EvalCommand($this->stdinReader('(php/+ 10 20)'));

        $this->expectOutputRegex('/30/');

        $exitCode = $command->run(
            $this->stubInput('-'),
            $this->stubOutput(),
        );

        self::assertSame(0, $exitCode);
    }

    public function test_eval_reads_multi_form_script_from_stdin(): void
    {
        $script = <<<'PHEL'
            (ns stdin-script)
            (def answer (php/+ 40 2))
            answer
            PHEL;

        $command = new EvalCommand($this->stdinReader($script));

        $this->expectOutputRegex('/42/');

        $exitCode = $command->run(
            $this->stubInput('-'),
            $this->stubOutput(),
        );

        self::assertSame(0, $exitCode);
    }

    private function createEvalCommand(): EvalCommand
    {
        return new EvalCommand();
    }

    private function stubInput(string $expression): InputInterface
    {
        $input = $this->createStub(InputInterface::class);
        $input->method('getArgument')->willReturn($expression);

        return $input;
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
