<?php

declare(strict_types=1);

namespace PhelTest\Integration\Run\Command\Repl;

use Gacela\Framework\Gacela;
use Override;
use Phel\Command\Application\TextExceptionPrinter;
use Phel\Command\Domain\ErrorLogInterface;
use Phel\Command\Domain\Exceptions\ExceptionArgsPrinter;
use Phel\Command\Domain\Exceptions\Extractor\FilePositionExtractor;
use Phel\Command\Infrastructure\SourceMapExtractor;
use Phel\Run\Domain\Repl\ReplCommandIoInterface;
use Phel\Run\Infrastructure\Command\ReplCommand;
use Phel\Run\RunFactory;
use Phel\Shared\ColorStyle;
use Phel\Shared\ColorStyleInterface;
use Phel\Shared\Munge;
use Phel\Shared\Printer\Printer;
use Phel\Shared\Printer\PrinterInterface;
use PhelTest\Integration\Run\Command\AbstractTestCommand;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Symfony\Component\Console\Input\InputInterface;

/**
 * The REPL seeds only `phel.core` (plus what the startup namespace requires)
 * eagerly; bundled `phel.*` modules outside that closure — here `phel.html`,
 * which the REPL startup never pulls in — load lazily on first reference.
 * These tests pin that behavior and the on-demand resolution that keeps fully
 * qualified references and `(require ...)` working.
 */
final class ReplLazyBundledNamespaceTest extends AbstractTestCommand
{
    private const string STARTUP_FILE = __DIR__ . '/../../../../../../resources/repl/startup.phel';

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
    public function test_boot_does_not_eagerly_load_unreferenced_bundle(): void
    {
        $io = $this->createReplTestIo();
        $io->setInputs(
            new InputLine('user:1> ', '(+ 1 2)'),
            new InputLine('user:2> ', '(contains-value? (ns-list) "phel.html")'),
            new InputLine('user:3> ', 'exit'),
        );
        $this->prepareRunFactory($io);

        new ReplCommand()->setReplStartupFile(self::STARTUP_FILE)->run(
            $this->createStub(InputInterface::class),
            $this->stubOutput(),
        );

        $outputs = $io->getRawOutputs();
        self::assertContains('3', $outputs, '(+ 1 2) must work at boot without any bundle other than core');
        self::assertContains('false', $outputs, 'phel.html must not be loaded eagerly at REPL boot');
        self::assertNotContains('true', $outputs);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_fully_qualified_reference_loads_bundle_on_demand(): void
    {
        $io = $this->createReplTestIo();
        $io->setInputs(
            new InputLine('user:1> ', '(phel\html/escape-html "<div>")'),
            new InputLine('user:2> ', '(contains-value? (ns-list) "phel.html")'),
            new InputLine('user:3> ', 'exit'),
        );
        $this->prepareRunFactory($io);

        new ReplCommand()->setReplStartupFile(self::STARTUP_FILE)->run(
            $this->createStub(InputInterface::class),
            $this->stubOutput(),
        );

        self::assertStringContainsString('&lt;div&gt;', $io->getOutputString(), 'A fully qualified bundle reference must resolve on demand');
        self::assertContains('true', $io->getRawOutputs(), 'phel.html must be loaded after a fully qualified reference');
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_require_loads_bundle_on_demand(): void
    {
        $io = $this->createReplTestIo();
        $io->setInputs(
            new InputLine('user:1> ', '(require [phel\html :as h])'),
            new InputLine('user:2> ', '(h/escape-html "<div>")'),
            new InputLine('user:3> ', 'exit'),
        );
        $this->prepareRunFactory($io);

        new ReplCommand()->setReplStartupFile(self::STARTUP_FILE)->run(
            $this->createStub(InputInterface::class),
            $this->stubOutput(),
        );

        self::assertStringContainsString('&lt;div&gt;', $io->getOutputString(), '(require ...) must load the bundle and expose it via its alias');
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_eval_failure_keeps_lazily_loaded_bundle_available(): void
    {
        $io = $this->createReplTestIo();
        $io->setInputs(
            // Load phel.html lazily.
            new InputLine('user:1> ', '(phel\html/escape-html "<a>")'),
            // Trigger an eval failure so the environment is restored.
            new InputLine('user:2> ', '(this-symbol-does-not-exist)'),
            // The lazily loaded bundle must survive the restore.
            new InputLine('user:3> ', '(phel\html/escape-html "<b>")'),
            new InputLine('user:4> ', 'exit'),
        );
        $this->prepareRunFactory($io);

        new ReplCommand()->setReplStartupFile(self::STARTUP_FILE)->run(
            $this->createStub(InputInterface::class),
            $this->stubOutput(),
        );

        self::assertStringContainsString('&lt;b&gt;', $io->getOutputString(), 'A lazily loaded bundle must stay available after an eval-failure restore');
    }

    private function createReplTestIo(): ReplTestIo
    {
        $exceptionPrinter = new TextExceptionPrinter(
            new ExceptionArgsPrinter(Printer::readable()),
            ColorStyle::noStyles(),
            new Munge(),
            new FilePositionExtractor(new SourceMapExtractor()),
            $this->createStub(ErrorLogInterface::class),
        );

        return new ReplTestIo($exceptionPrinter);
    }

    private function prepareRunFactory(ReplCommandIoInterface $io): void
    {
        Gacela::overrideExistingResolvedClass(
            RunFactory::class,
            new class($io) extends RunFactory {
                public function __construct(private readonly ReplCommandIoInterface $io) {}

                public function createColorStyle(): ColorStyleInterface
                {
                    return ColorStyle::noStyles();
                }

                public function createPrinter(): PrinterInterface
                {
                    return Printer::nonReadable();
                }

                public function createReplCommandIo(): ReplCommandIoInterface
                {
                    return $this->io;
                }
            },
        );
    }
}
