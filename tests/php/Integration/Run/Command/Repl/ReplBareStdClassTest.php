<?php

declare(strict_types=1);

namespace PhelTest\Integration\Run\Command\Repl;

use Phel\Run\Infrastructure\Command\ReplCommand;
use PhelTest\Integration\Run\Command\AbstractTestCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function array_keys;

final class ReplBareStdClassTest extends AbstractTestCommand
{
    use ReplCommandTestTrait;

    public function test_repl_resolves_bare_stdclass(): void
    {
        $io = $this->createReplTestIo();
        $io->setInputs(
            new InputLine('user:1> ', '(php/new stdClass)'),
            new InputLine('user:2> ', '(new stdClass)'),
            new InputLine('user:3> ', 'exit'),
        );

        $this->prepareRunFactory($io);

        $exitCode = new ReplCommand()->run(
            $this->createStub(InputInterface::class),
            $this->createStub(OutputInterface::class),
        );

        self::assertSame(0, $exitCode);
        self::assertCount(
            2,
            array_keys($io->getOutputLines(), 'Printer cannot print this type: stdClass', strict: true),
        );
    }

}
