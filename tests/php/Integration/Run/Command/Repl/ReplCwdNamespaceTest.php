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

final class ReplCwdNamespaceTest extends AbstractTestCommand
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
        file_put_contents($this->tempDir . '/my-module.phel', <<<'PHEL'
(ns my-module)

(defn hello [x]
  (str "(module.phel at cwd): " x))
PHEL);
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
    public function test_resolves_namespaces_from_cwd(): void
    {
        $io = $this->createReplTestIo();
        $io->setInputs(
            new InputLine('user:1> ', '(require my-module)'),
            new InputLine('user:2> ', '(my-module/hello "foo")'),
            new InputLine('user:3> ', 'exit'),
        );
        $this->prepareRunFactory($io);

        $repl = new ReplCommand();
        $repl->run(
            $this->createStub(InputInterface::class),
            $this->stubOutput(),
        );

        $output = $io->getOutputString();
        self::assertStringContainsString('my-module', $output);
        self::assertStringContainsString('(module.phel at cwd): foo', $output);
    }

}
