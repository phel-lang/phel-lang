<?php

declare(strict_types=1);

namespace Phel\Run\Infrastructure\Command;

use Gacela\Framework\DocBlockResolverAwareTrait;
use Phel\Compiler\Domain\Exceptions\CompilerException;
use Phel\Compiler\Domain\Parser\Exceptions\UnfinishedParserException;
use Phel\Compiler\Infrastructure\CompileOptions;
use Phel\Lang\Registry;
use Phel\Run\Domain\Repl\ExitException;
use Phel\Run\Domain\Repl\InputResult;
use Phel\Run\RunFacade;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

use function count;
use function dirname;

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

    private ?string $replStartupFile = null;

    /** @var string[] */
    private array $inputBuffer = [];
    private int $lineNumber = 1;
    private ?InputResult $previousResult = null;

    public function setReplStartupFile(string $replStartupFile): self
    {
        $this->replStartupFile = $replStartupFile;

        return $this;
    }

    public function getReplStartupFile(): string
    {
        return $this->replStartupFile ?? $this->getFacade()->getReplStartupFile();
    }

    protected function configure(): void
    {
        $this->setName('repl')
            ->setDescription('Start a Repl.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->previousResult = InputResult::empty();
        $this->replStartupFile = $this->getReplStartupFile();

        $this->getFacade()->getReplCommandIo()->readHistory();
        $this->getFacade()->getReplCommandIo()->writeln(
            $this->getFacade()->getColorStyle()->yellow('Welcome to the Phel Repl')
        );
        $this->getFacade()->getReplCommandIo()->writeln('Type "exit" or press Ctrl-D to exit.');

        $this->getFacade()->registerExceptionHandler();

        if ($this->replStartupFile && file_exists($this->replStartupFile)) {
            $namespace = $this->getFacade()
                ->getNamespaceFromFile($this->replStartupFile)
                ->getNamespace();

            $srcDirectories = [
                dirname($this->replStartupFile),
                ...$this->getFacade()->getSourceDirectories(),
                ...$this->getFacade()->getTestDirectories(),
                ...$this->getFacade()->getVendorSourceDirectories(),
            ];
            $namespaceInformation = $this->getFacade()->getDependenciesForNamespace($srcDirectories, [$namespace, 'phel\\core']);

            foreach ($namespaceInformation as $info) {
                $this->getFacade()->evalFile($info);
            }

            // Ugly Hack: Set source directories for the repl
            Registry::getInstance()->addDefinition('phel\\repl', 'src-dirs', $srcDirectories);
        }

        $this->loopReadLineAndAnalyze();

        return self::SUCCESS;
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
                $this->getFacade()->getReplCommandIo()->writeln($this->getFacade()->getColorStyle()->red($e->getMessage()));
                $this->getFacade()->getReplCommandIo()->writeln($e->getTraceAsString());
            }
        }

        $this->getFacade()->getReplCommandIo()->writeln($this->getFacade()->getColorStyle()->yellow('Bye!'));
    }

    private function addLineFromPromptToBuffer(): void
    {
        if ($this->getFacade()->getReplCommandIo()->isBracketedPasteSupported()) {
            $this->getFacade()->getReplCommandIo()->write(self::ENABLE_BRACKETED_PASTE);
        }

        $isInitialInput = empty($this->inputBuffer);
        $prompt = $isInitialInput ? self::INITIAL_PROMPT : self::OPEN_PROMPT;
        $input = $this->getFacade()->getReplCommandIo()->readline(sprintf($prompt, $this->lineNumber));

        if ($this->getFacade()->getReplCommandIo()->isBracketedPasteSupported()) {
            $this->getFacade()->getReplCommandIo()->write(self::DISABLE_BRACKETED_PASTE);
        }

        ++$this->lineNumber;

        if ($input === null && $isInitialInput) {
            // Ctrl+D will exit the repl
            $this->inputBuffer[] = self::EXIT_REPL;
        } elseif ($input === null && !$isInitialInput) {
            // Ctrl+D will empty the buffer
            $this->inputBuffer = [];
            $this->getFacade()->getReplCommandIo()->writeln();
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

        /** @psalm-suppress PossiblyNullReference */
        $fullInput = $this->previousResult->readBuffer($this->inputBuffer);

        try {
            $options = (new CompileOptions())
                ->setStartingLine($this->lineNumber - count($this->inputBuffer));

            $result = $this->getFacade()->eval($fullInput, $options);
            $this->previousResult = InputResult::fromAny($result);

            $this->addHistory($fullInput);
            $this->getFacade()->getReplCommandIo()->writeln($this->getFacade()->getPrinter()->print($result));

            $this->inputBuffer = [];
        } catch (UnfinishedParserException $e) {
            // The input is valid but more input is missing to finish the parsing.
        } catch (CompilerException $e) {
            $this->getFacade()->getReplCommandIo()->writeLocatedException($e->getNestedException(), $e->getCodeSnippet());
            $this->addHistory($fullInput);
            $this->inputBuffer = [];
        } catch (Throwable $e) {
            $this->getFacade()->getReplCommandIo()->writeStackTrace($e);
            $this->addHistory($fullInput);
            $this->inputBuffer = [];
        }
    }

    private function addHistory(string $input): void
    {
        if ($input !== '') {
            $this->getFacade()->getReplCommandIo()->addHistory($input);
        }
    }
}
