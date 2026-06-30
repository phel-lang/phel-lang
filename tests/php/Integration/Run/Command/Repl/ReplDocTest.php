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

final class ReplDocTest extends AbstractTestCommand
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
    public function test_doc_prints_signature_description_and_example(): void
    {
        $io = $this->createReplTestIo();
        $io->setInputs(
            new InputLine('user:1> ', '(defn add-two "Adds 2 to x." {:example "(add-two 5)"} [x] (+ x 2))'),
            new InputLine('user:2> ', '(doc add-two)'),
            new InputLine('user:3> ', 'exit'),
        );
        $this->prepareRunFactory($io);

        // `doc` prints via `println`, which writes to stdout rather than the
        // REPL IO buffer, so capture both streams.
        ob_start();
        new ReplCommand()->run(
            $this->createStub(InputInterface::class),
            $this->stubOutput(),
        );
        $output = $io->getOutputString() . ob_get_clean();
        self::assertStringContainsString('(add-two x)', $output);
        self::assertStringContainsString('Adds 2 to x.', $output);
        // The :example metadata is now rendered under its own heading (#2645).
        self::assertStringContainsString("Example:\n(add-two 5)", $output);
    }
}
