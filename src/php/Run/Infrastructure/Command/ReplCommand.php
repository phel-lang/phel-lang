<?php

declare(strict_types=1);

namespace Phel\Run\Infrastructure\Command;

use Gacela\Framework\DocBlockResolverAwareTrait;
use Phel\Compiler\Domain\Exceptions\CompilerException;
use Phel\Compiler\Domain\Parser\Exceptions\UnfinishedParserException;
use Phel\Compiler\Infrastructure\CompileOptions;
use Phel\Lang\Registry;
use Phel\Printer\PrinterInterface;
use Phel\Run\Domain\Repl\ColorStyleInterface;
use Phel\Run\Domain\Repl\ExitException;
use Phel\Run\Domain\Repl\InputResult;
use Phel\Run\Domain\Repl\ReplCommandIoInterface;
use Phel\Run\RunFacade;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

use function count;
use function dirname;
use function is_string;

/**
 * @method RunFacade getFacade()
 */
final class ReplCommand extends Command
{
    use DocBlockResolverAwareTrait;

    private const ENABLE_BRACKETED_PASTE = "\e[?2004h";
    private const DISABLE_BRACKETED_PASTE = "\e[?2004l";
    private const INITIAL_PROMPT = 'phel:%d> ';
    private const OPEN_PROMPT = '....:%d> ';
    private const EXIT_REPL = 'exit';

    private InputResult $previousResult;

    private ReplCommandIoInterface $io;

    private ColorStyleInterface $colorStyle;

    private PrinterInterface $printer;

    private ?string $replStartupFile = null;

    /** @var string[] */
    private array $inputBuffer = [];

    private int $lineNumber = 1;

    public function __construct()
    {
        parent::__construct('repl');

        $this->previousResult = InputResult::empty();
        $this->io = $this->getFacade()->getReplCommandIo();
        $this->colorStyle = $this->getFacade()->getColorStyle();
        $this->printer = $this->getFacade()->getPrinter();
    }

    /**
     * @interal for testing purposes
     */
    public function setReplStartupFile(string $replStartupFile): self
    {
        $this->replStartupFile = $replStartupFile;

        return $this;
    }

    protected function configure(): void
    {
        $this->setDescription('Start a Repl.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->replStartupFile = $this->getReplStartupFile();

        $this->io->readHistory();
        $this->io->writeln($this->colorStyle->yellow('Welcome to the Phel Repl'));
        $this->io->writeln('Type "exit" or press Ctrl-D to exit.');

        $this->getFacade()->registerExceptionHandler();
        $this->loadAllPhelNamespaces();
        $this->loopReadLineAndAnalyze();

        return self::SUCCESS;
    }

    private function getReplStartupFile(): string
    {
        return $this->replStartupFile ?? $this->getFacade()->getReplStartupFile();
    }

    private function loadAllPhelNamespaces(): void
    {
        if (!is_string($this->replStartupFile) || !file_exists($this->replStartupFile)) {
            return;
        }
        $namespace = $this->getFacade()
            ->getNamespaceFromFile($this->replStartupFile)
            ->getNamespace();

        $srcDirectories = [
            dirname($this->replStartupFile),
            ...$this->getFacade()->getAllPhelDirectories(),
        ];
        $namespaceInformation = $this->getFacade()->getDependenciesForNamespace(
            $srcDirectories,
            [$namespace, 'phel\\core']
        );

        foreach ($namespaceInformation as $info) {
            $this->getFacade()->evalFile($info);
        }

        // Ugly Hack: Set source directories for the repl
        Registry::getInstance()->addDefinition('phel\\repl', 'src-dirs', $srcDirectories);
    }

    private function loopReadLineAndAnalyze(): void
    {
        while (true) {
            try {
                $this->addLineFromPromptToBuffer();
                $this->checkExitInputBuffer();
                $this->analyzeInputBuffer();
            } catch (ExitException $e) {
                break;
            } catch (Throwable $e) {
                $this->inputBuffer = [];
                $this->io->writeln($this->colorStyle->red($e->getMessage()));
                $this->io->writeln($e->getTraceAsString());
            }
        }

        $this->io->writeln($this->colorStyle->yellow('Bye!'));
    }

    private function addLineFromPromptToBuffer(): void
    {
        if ($this->io->isBracketedPasteSupported()) {
            $this->io->write(self::ENABLE_BRACKETED_PASTE);
        }

        $isInitialInput = $this->inputBuffer === [];
        $prompt = $isInitialInput ? self::INITIAL_PROMPT : self::OPEN_PROMPT;
        $input = $this->io->readline(sprintf($prompt, $this->lineNumber));

        if ($this->io->isBracketedPasteSupported()) {
            $this->io->write(self::DISABLE_BRACKETED_PASTE);
        }

        ++$this->lineNumber;

        if ($input === null && $isInitialInput) {
            // Ctrl+D will exit the repl
            $this->inputBuffer[] = self::EXIT_REPL;
        } elseif ($input === null && !$isInitialInput) {
            // Ctrl+D will empty the buffer
            $this->inputBuffer = [];
            $this->io->writeln();
        } else {
            $this->inputBuffer[] = $input;
        }
    }

    /**
     * @throws ExitException
     */
    private function checkExitInputBuffer(): void
    {
        $firstInput = $this->inputBuffer[0] ?? '';

        if ($firstInput === self::EXIT_REPL) {
            throw ExitException::fromRepl();
        }
    }

    private function analyzeInputBuffer(): void
    {
        if (empty($this->inputBuffer)) {
            return;
        }
        $fullInput = $this->previousResult->readBuffer($this->inputBuffer);

        try {
            $options = (new CompileOptions())
                ->setStartingLine($this->lineNumber - count($this->inputBuffer));

            $result = $this->getFacade()->eval($fullInput, $options);
            $this->previousResult = InputResult::fromAny($result);

            $this->addHistory($fullInput);
            $this->io->writeln($this->printer->print($result));

            $this->inputBuffer = [];
        } catch (UnfinishedParserException $e) {
            // The input is valid but more input is missing to finish the parsing.
        } catch (CompilerException $e) {
            $this->io->writeLocatedException($e->getNestedException(), $e->getCodeSnippet());
            $this->addHistory($fullInput);
            $this->inputBuffer = [];
        } catch (Throwable $e) {
            $this->io->writeStackTrace($e);
            $this->addHistory($fullInput);
            $this->inputBuffer = [];
        }
    }

    private function addHistory(string $input): void
    {
        if ($input !== '') {
            $this->io->addHistory($input);
        }
    }
}
