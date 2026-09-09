<?php

declare(strict_types=1);

namespace PhelTest\Integration\Run\Command\Repl;

use Phel\Run\Infrastructure\Command\ReplCommand;
use PhelTest\Integration\Run\Command\AbstractTestCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function str_contains;

/**
 * The banner tells the user how to leave, and a Lisp user reaches for the call
 * form whatever the banner says. Both have to work, and the banner has to name
 * a form the session actually accepts.
 */
final class ReplExitTest extends AbstractTestCommand
{
    use ReplCommandTestTrait;

    /**
     * @return list<array{string}>
     */
    public static function provideEndsTheSession(): array
    {
        return [
            ['exit'],
            ['quit'],
            ['(exit)'],
            ['(quit)'],
            ['  (exit)  '],
            ['( exit )'],
        ];
    }

    /**
     * @dataProvider provideEndsTheSession
     */
    public function test_ends_the_session_with_status_zero(string $input): void
    {
        $io = $this->createReplTestIo();
        $io->setInputs(new InputLine('user:1> ', $input));

        $this->prepareRunFactory($io);

        $exitCode = new ReplCommand()->run(
            $this->createStub(InputInterface::class),
            $this->createStub(OutputInterface::class),
        );

        self::assertSame(0, $exitCode);
        self::assertContains('Bye!', $io->getOutputLines());
    }

    public function test_exit_with_an_argument_becomes_the_status(): void
    {
        $io = $this->createReplTestIo();
        $io->setInputs(new InputLine('user:1> ', '(exit 2)'));

        $this->prepareRunFactory($io);

        $exitCode = new ReplCommand()->run(
            $this->createStub(InputInterface::class),
            $this->createStub(OutputInterface::class),
        );

        self::assertSame(2, $exitCode);
    }

    public function test_the_banner_names_a_form_the_session_accepts(): void
    {
        $io = $this->createReplTestIo();
        $io->setInputs(new InputLine('user:1> ', '(exit)'));

        $this->prepareRunFactory($io);

        new ReplCommand()->run(
            $this->createStub(InputInterface::class),
            $this->createStub(OutputInterface::class),
        );

        $banner = null;
        foreach ($io->getOutputLines() as $line) {
            if (str_contains($line, 'press Ctrl-D to exit')) {
                $banner = $line;
                break;
            }
        }

        self::assertNotNull($banner, 'the repl printed no exit banner');
        self::assertStringContainsString('(exit)', $banner);
    }

    /**
     * @return list<array{string}>
     */
    public static function provideIsOrdinaryCode(): array
    {
        return [
            // A symbol that merely starts with exit is not the command.
            ['(exit-code)'],
            ['exits'],
            // The word inside a string or an argument list is not the command.
            ['(println "exit")'],
            ['(map exit xs)'],
            // A call form the command does not define stays a normal form, so
            // it fails as such rather than silently ending the session.
            ['(exit x)'],
            ['(exit 2 3)'],
        ];
    }

    /**
     * @dataProvider provideIsOrdinaryCode
     */
    public function test_leaves_ordinary_code_alone(string $input): void
    {
        $io = $this->createReplTestIo();
        $io->setInputs(
            new InputLine('user:1> ', $input),
            new InputLine('user:2> ', 'exit'),
        );

        $this->prepareRunFactory($io);

        $exitCode = new ReplCommand()->run(
            $this->createStub(InputInterface::class),
            $this->createStub(OutputInterface::class),
        );

        // It reached the second line to leave, so the first did not end the
        // session; whether it evaluated or errored is not this test's business.
        self::assertSame(0, $exitCode);
        self::assertStringContainsString('user:2> exit', $io->getOutputString());
    }
}
