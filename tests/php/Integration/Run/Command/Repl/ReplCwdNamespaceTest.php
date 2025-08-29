<?php

declare(strict_types=1);

namespace PhelTest\Integration\Run\Command\Repl;

use FilesystemIterator;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use Override;
use Phel\Command\Application\TextExceptionPrinter;
use Phel\Command\Domain\ErrorLogInterface;
use Phel\Command\Domain\Exceptions\ExceptionArgsPrinter;
use Phel\Command\Domain\Exceptions\Extractor\FilePositionExtractor;
use Phel\Command\Infrastructure\SourceMapExtractor;
use Phel\Compiler\Application\Munge;
use Phel\Printer\Printer;
use Phel\Printer\PrinterInterface;
use Phel\Run\Domain\Repl\ColorStyle;
use Phel\Run\Domain\Repl\ColorStyleInterface;
use Phel\Run\Domain\Repl\ReplCommandIoInterface;
use Phel\Run\Infrastructure\Command\ReplCommand;
use Phel\Run\RunFactory;
use PhelTest\Integration\Run\Command\AbstractTestCommand;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Console\Input\InputInterface;

final class ReplCwdNamespaceTest extends AbstractTestCommand
{
    private string $previousCwd = '';

    private string $tempDir = '';

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->previousCwd = getcwd() ?: '';
        $this->tempDir = sys_get_temp_dir() . '/phel-repl-cwd-' . uniqid('', true);
        mkdir($this->tempDir, 0777, true);
        chdir($this->tempDir);

        file_put_contents('my-module.phel', <<<'PHEL'
(ns my-module)

(defn hello [x]
  (str "(module.phel at cwd): " x))
PHEL);

        Gacela::bootstrap($this->tempDir, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
        });
    }

    #[Override]
    protected function tearDown(): void
    {
        chdir($this->previousCwd);
        if (is_dir($this->tempDir)) {
            $it = new RecursiveDirectoryIterator($this->tempDir, FilesystemIterator::SKIP_DOTS);
            $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($files as $file) {
                $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
            }

            rmdir($this->tempDir);
        }
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_resolves_namespaces_from_cwd(): void
    {
        $io = $this->createReplTestIo();
        $io->setInputs(
            new InputLine('phel:1> ', '(require my-module)'),
            new InputLine('phel:2> ', '(my-module/hello "foo")'),
            new InputLine('phel:3> ', 'exit'),
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
                public function __construct(private readonly ReplCommandIoInterface $io)
                {
                }

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
