<?php

declare(strict_types=1);

namespace PhelTest\Integration\Run\Command\Repl;

use Gacela\Framework\Gacela;
use Override;
use Phel\Run\Infrastructure\Command\ReplCommand;
use PhelTest\Integration\Run\Command\AbstractTestCommand;
use PhelTest\Support\CapturesCompilerWarningsTrait;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Symfony\Component\Console\Input\InputInterface;

/**
 * The prompt refers 20-odd `phel.repl` names into every namespace it analyses,
 * so `doc`, `require` and friends are exactly the ordinary words someone
 * redefines at a REPL. Before #2897 the `def` succeeded and was unreachable
 * under its own name.
 */
final class ReplReferShadowTest extends AbstractTestCommand
{
    use CapturesCompilerWarningsTrait;
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
        $this->stopCapturingCompilerWarnings();
        chdir($this->previousCwd);
        $this->cleanupContainerTempDirs();
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_a_def_at_the_prompt_beats_the_injected_refer_of_the_same_name(): void
    {
        $this->startCapturingCompilerWarnings();
        $io = $this->createReplTestIo();
        $io->setInputs(
            new InputLine('user:1> ', '(def doc 1)'),
            new InputLine('user:2> ', 'doc'),
            new InputLine('user:3> ', 'user/doc'),
            new InputLine('user:4> ', 'exit'),
        );
        $this->prepareRunFactory($io);

        ob_start();
        new ReplCommand()->run(
            $this->createStub(InputInterface::class),
            $this->stubOutput(),
        );
        $output = $io->getOutputString() . ob_get_clean();

        self::assertStringNotContainsString('<function:doc>', $output);
        self::assertStringContainsString("#'user/doc", $output);

        $captured = $this->capturedCompilerWarnings();
        self::assertCount(1, $captured);
        self::assertStringContainsString("doc already refers to: #'phel.repl/doc", $captured[0]);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_an_injected_refer_the_user_never_defines_still_resolves(): void
    {
        $io = $this->createReplTestIo();
        $io->setInputs(
            new InputLine('user:1> ', '(defn add-two "Adds 2 to x." {:example "(add-two 5)"} [x] (+ x 2))'),
            new InputLine('user:2> ', '(doc add-two)'),
            new InputLine('user:3> ', 'exit'),
        );
        $this->prepareRunFactory($io);

        ob_start();
        new ReplCommand()->run(
            $this->createStub(InputInterface::class),
            $this->stubOutput(),
        );
        $output = $io->getOutputString() . ob_get_clean();

        self::assertStringContainsString('(add-two x)', $output);
    }
}
