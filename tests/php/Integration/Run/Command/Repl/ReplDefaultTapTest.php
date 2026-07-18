<?php

declare(strict_types=1);

namespace PhelTest\Integration\Run\Command\Repl;

use Gacela\Framework\Gacela;
use Override;
use Phel\Run\Infrastructure\Command\ReplCommand;
use PhelTest\Integration\Run\Command\AbstractTestCommand;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Symfony\Component\Console\Input\InputInterface;

final class ReplDefaultTapTest extends AbstractTestCommand
{
    use ReplCommandTestTrait;

    private string $previousCwd = '';

    private string $tempDir = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousCwd = getcwd() ?: '';
        $this->tempDir = $this->containerTempDir();
        chdir($this->tempDir);
        Gacela::bootstrap($this->tempDir);
    }

    #[Override]
    protected function tearDown(): void
    {
        chdir($this->previousCwd);
        $this->cleanupContainerTempDirs();
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_tap_output_is_visible_by_default(): void
    {
        $io = $this->createReplTestIo();
        $io->setInputs(
            new InputLine('user:1> ', '(tap> :hello)'),
            new InputLine('user:2> ', 'exit'),
        );
        $this->prepareRunFactory($io);

        // print-tap writes via php/print, which goes to real stdout rather
        // than the captured ReplTestIo — buffer it.
        ob_start();
        try {
            new ReplCommand()->run(
                $this->createStub(InputInterface::class),
                $this->stubOutput(),
            );
        } finally {
            $stdout = ob_get_clean();
        }

        self::assertStringContainsString('tap> ', (string) $stdout, 'default tap must print a "tap> " line');
        self::assertStringContainsString(':hello', (string) $stdout, 'default tap must print the tapped value');
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_default_tap_can_be_removed(): void
    {
        $io = $this->createReplTestIo();
        $io->setInputs(
            new InputLine('user:1> ', '(remove-tap phel.repl/print-tap)'),
            new InputLine('user:2> ', '(tap> :silent)'),
            new InputLine('user:3> ', 'exit'),
        );
        $this->prepareRunFactory($io);

        ob_start();
        try {
            new ReplCommand()->run(
                $this->createStub(InputInterface::class),
                $this->stubOutput(),
            );
        } finally {
            $stdout = ob_get_clean();
        }

        self::assertStringNotContainsString('tap> ', (string) $stdout, 'removed tap must not print');
        self::assertStringNotContainsString(':silent', (string) $stdout, 'removed tap must not print the tapped value');
    }
}
