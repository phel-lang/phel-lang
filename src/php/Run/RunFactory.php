<?php

declare(strict_types=1);

namespace Phel\Run;

use Gacela\Framework\AbstractFactory;
use Phel\Run\Application\BundledNamespaceDetector;
use Phel\Run\Application\BundledNamespaces;
use Phel\Run\Application\CompileExecutor;
use Phel\Run\Application\EntryPointDetector;
use Phel\Run\Application\EvalExecutor;
use Phel\Run\Application\FileRunner;
use Phel\Run\Application\NamespaceLoader;
use Phel\Run\Application\NamespaceRunner;
use Phel\Run\Application\NamespacesLoader;
use Phel\Run\Application\ReplHistoryPathResolver;
use Phel\Run\Application\StructuredEvaluator;
use Phel\Run\Application\Test\CpuCountDetector;
use Phel\Run\Application\Test\ParallelTestOrchestrator;
use Phel\Run\Domain\Repl\Hint\ArgumentCountHint;
use Phel\Run\Domain\Repl\Hint\NotCallableHint;
use Phel\Run\Domain\Repl\Hint\ReplHintInterface;
use Phel\Run\Domain\Repl\Hint\UndefinedSymbolHint;
use Phel\Run\Domain\Repl\ReplCommandFallbackIo;
use Phel\Run\Domain\Repl\ReplCommandIoInterface;
use Phel\Run\Domain\Repl\ReplCommandSystemIo;
use Phel\Run\Domain\Repl\ReplErrorFormatter;
use Phel\Run\Domain\Repl\ReplHistory;
use Phel\Run\Domain\Repl\ReplPrompt;
use Phel\Run\Domain\Runner\NamespaceCollector;
use Phel\Run\Domain\Runner\NamespaceRunnerInterface;
use Phel\Run\Domain\StdinReaderInterface;
use Phel\Run\Infrastructure\PhpStdinReader;
use Phel\Shared\ColorStyle;
use Phel\Shared\ColorStyleInterface;
use Phel\Shared\Facade\ApiFacadeInterface;
use Phel\Shared\Facade\BuildFacadeInterface;
use Phel\Shared\Facade\CommandFacadeInterface;
use Phel\Shared\Facade\CompilerFacadeInterface;
use Phel\Shared\Facade\ConsoleFacadeInterface;
use Phel\Shared\Printer\Printer;
use Phel\Shared\Printer\PrinterInterface;

use function extension_loaded;

/**
 * @extends AbstractFactory<RunConfig>
 */
class RunFactory extends AbstractFactory
{
    public function createNamespaceRunner(): NamespaceRunnerInterface
    {
        return new NamespaceRunner(
            $this->getCommandFacade(),
            $this->getBuildFacade(),
        );
    }

    public function getCommandFacade(): CommandFacadeInterface
    {
        return $this->getProvidedDependency(RunProvider::FACADE_COMMAND);
    }

    public function getBuildFacade(): BuildFacadeInterface
    {
        return $this->getProvidedDependency(RunProvider::FACADE_BUILD);
    }

    public function getCompilerFacade(): CompilerFacadeInterface
    {
        return $this->getProvidedDependency(RunProvider::FACADE_COMPILER);
    }

    public function createNamespaceCollector(): NamespaceCollector
    {
        return new NamespaceCollector(
            $this->getBuildFacade(),
            $this->getCommandFacade(),
        );
    }

    public function createBundledNamespaces(): BundledNamespaces
    {
        return new BundledNamespaces(
            $this->getBuildFacade(),
            $this->getCommandFacade(),
        );
    }

    public function createBundledNamespaceDetector(): BundledNamespaceDetector
    {
        return new BundledNamespaceDetector(
            $this->createBundledNamespaces(),
        );
    }

    public function createColorStyle(): ColorStyleInterface
    {
        return ColorStyle::withStyles();
    }

    public function createPrinter(): PrinterInterface
    {
        return Printer::readableWithColor();
    }

    /**
     * Builds the REPL input handler. Prefers the readline-backed
     * {@see ReplCommandSystemIo} (line editing plus persisted history) when the
     * `readline` extension is loaded, falling back to {@see ReplCommandFallbackIo}
     * for environments without it (e.g. CI, web SAPI).
     */
    public function createReplCommandIo(): ReplCommandIoInterface
    {
        if (extension_loaded('readline')) {
            return new ReplCommandSystemIo(
                $this->createReplHistoryPathResolver()->resolve(),
                $this->getCommandFacade(),
                $this->getApiFacade(),
                $this->createReplErrorFormatter(),
            );
        }

        return new ReplCommandFallbackIo(
            $this->getCommandFacade(),
            $this->createReplErrorFormatter(),
        );
    }

    public function createReplHistoryPathResolver(): ReplHistoryPathResolver
    {
        return new ReplHistoryPathResolver($this->getConfig()->getAppRootDir());
    }

    public function createReplErrorFormatter(): ReplErrorFormatter
    {
        return new ReplErrorFormatter(
            $this->createReplHints(),
            $this->getCommandFacade()->getExceptionPrinter(),
            $this->createColorStyle(),
        );
    }

    /**
     * @return list<ReplHintInterface>
     */
    public function createReplHints(): array
    {
        return [
            new NotCallableHint(),
            new ArgumentCountHint(),
            new UndefinedSymbolHint(),
        ];
    }

    public function createReplHistory(): ReplHistory
    {
        return new ReplHistory($this->getCompilerFacade()->getGlobalEnvironment());
    }

    public function createReplPrompt(): ReplPrompt
    {
        return new ReplPrompt($this->getCompilerFacade());
    }

    public function createNamespacesLoader(): NamespacesLoader
    {
        return new NamespacesLoader(
            $this->getCommandFacade(),
            $this->getBuildFacade(),
        );
    }

    public function getApiFacade(): ApiFacadeInterface
    {
        return $this->getProvidedDependency(RunProvider::FACADE_API);
    }

    public function getConsoleFacade(): ConsoleFacadeInterface
    {
        return $this->getProvidedDependency(RunProvider::FACADE_CONSOLE);
    }

    public function createEvalExecutor(): EvalExecutor
    {
        return new EvalExecutor(
            $this->createReplCommandIo(),
            $this->createColorStyle(),
            $this->createPrinter(),
            $this->getCompilerFacade(),
        );
    }

    public function createCompileExecutor(): CompileExecutor
    {
        return new CompileExecutor(
            $this->getCompilerFacade(),
        );
    }

    public function createNamespaceLoader(): NamespaceLoader
    {
        return new NamespaceLoader(
            $this->getBuildFacade(),
            $this->getCommandFacade(),
            $this->getCompilerFacade(),
            $this->createBundledNamespaces(),
            $this->getConfig()->getReplStartupFile(),
        );
    }

    public function createFileRunner(): FileRunner
    {
        return new FileRunner(
            $this->getBuildFacade(),
            $this->getCommandFacade(),
            $this->createBundledNamespaceDetector(),
        );
    }

    public function createStructuredEvaluator(): StructuredEvaluator
    {
        return new StructuredEvaluator(
            $this->getCompilerFacade(),
        );
    }

    public function createEntryPointDetector(): EntryPointDetector
    {
        return new EntryPointDetector(
            $this->getCommandFacade(),
        );
    }

    public function createStdinReader(): StdinReaderInterface
    {
        return new PhpStdinReader();
    }

    public function createParallelTestOrchestrator(): ParallelTestOrchestrator
    {
        return new ParallelTestOrchestrator(
            PHP_BINARY,
            $this->resolvePhelBinaryPath(),
        );
    }

    public function createCpuCountDetector(): CpuCountDetector
    {
        return new CpuCountDetector();
    }

    private function resolvePhelBinaryPath(): string
    {
        $script = $_SERVER['SCRIPT_FILENAME'] ?? $_SERVER['argv'][0] ?? '';
        if ($script !== '') {
            return $script;
        }

        return __DIR__ . '/../../../bin/phel';
    }
}
