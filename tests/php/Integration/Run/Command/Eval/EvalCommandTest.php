<?php

declare(strict_types=1);

namespace PhelTest\Integration\Run\Command\Eval;

use Phel\Phel;
use Phel\Run\Infrastructure\Command\EvalCommand;
use PhelTest\Integration\Run\Command\AbstractTestCommand;
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
}
