<?php

declare(strict_types=1);

namespace Phel\Run\Infrastructure\Command;

use Gacela\Framework\ServiceResolver\ServiceMap;
use Gacela\Framework\ServiceResolverAwareTrait;
use Phel;
use Phel\Compiler\Domain\Evaluator\Exceptions\CompiledCodeIsMalformedException;
use Phel\Compiler\Domain\Exceptions\CompilerException;
use Phel\Compiler\Domain\Parser\Exceptions\UnfinishedParserException;
use Phel\Compiler\Infrastructure\CompileOptions;
use Phel\Printer\PrinterInterface;
use Phel\Run\Domain\Repl\ExitException;
use Phel\Run\Domain\Repl\InputResult;
use Phel\Run\Domain\Repl\ReplCommandIoInterface;
use Phel\Run\RunConfig;
use Phel\Run\RunFacade;
use Phel\Run\RunFactory;
use Phel\Shared\ColorStyleInterface;
use Phel\Shared\CompilerConstants;
use Phel\Shared\Facade\CompilerFacadeInterface;
use Phel\Shared\ReplConstants;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

use function array_reverse;
use function count;
use function explode;
use function sprintf;

#[ServiceMap(method: 'getFacade', className: RunFacade::class)]
#[ServiceMap(method: 'getFactory', className: RunFactory::class)]
#[ServiceMap(method: 'getConfig', className: RunConfig::class)]
final class ReplCommand extends Command
{
    use ServiceResolverAwareTrait;

    private const string ENABLE_BRACKETED_PASTE = "\e[?2004h";

    private const string DISABLE_BRACKETED_PASTE = "\e[?2004l";

    private const string INITIAL_PROMPT = 'phel:%d> ';

    private const string OPEN_PROMPT = '....:%d> ';

    private const string EXIT_REPL = 'exit';

    private InputResult $previousResult;

    private readonly ReplCommandIoInterface $io;

    private readonly ColorStyleInterface $style;

    private readonly PrinterInterface $printer;

    private readonly CompilerFacadeInterface $compilerFacade;

    private ?string $replStartupFile = null;

    /** @var list<string> */
    private array $inputBuffer = [];

    private int $lineNumber = 1;

    public function __construct()
    {
        parent::__construct('repl');

        $this->previousResult = InputResult::empty();
        $this->io = $this->getFactory()->createReplCommandIo();
        $this->style = $this->getFactory()->createColorStyle();
        $this->printer = $this->getFactory()->createPrinter();
        $this->compilerFacade = $this->getFactory()->getCompilerFacade();
    }

    /**
     * @internal for testing purposes
     */
    public function setReplStartupFile(string $replStartupFile): self
    {
        $this->replStartupFile = $replStartupFile;

        return $this;
    }

    protected function configure(): void
    {
        $this->setDescription('Start a Repl');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->replStartupFile = $this->getReplStartupFile();

        $this->io->readHistory();

        $this->io->writeln($this->style->yellow(
            sprintf('Welcome to the Phel Repl (%s)', $this->getFacade()->getVersion()),
        ));

        $this->io->writeln('Type "exit" or press Ctrl-D to exit.');

        try {
            $this->getFacade()->loadPhelNamespaces($this->replStartupFile);
            Phel::addDefinition(CompilerConstants::PHEL_CORE_NAMESPACE, ReplConstants::REPL_MODE, true);

            $this->loopReadLineAndAnalyze();

            return self::SUCCESS;
        } catch (Throwable $throwable) {
            $this->io->writeStackTrace($throwable);
            return self::FAILURE;
        } finally {
            Phel::addDefinition(CompilerConstants::PHEL_CORE_NAMESPACE, ReplConstants::REPL_MODE, false);
        }
    }

    private function getReplStartupFile(): string
    {
        return $this->replStartupFile ?? $this->getConfig()->getReplStartupFile();
    }

    private function loopReadLineAndAnalyze(): void
    {
        while (true) {
            try {
                $this->addLineFromPromptToBuffer();
                $this->checkExitInputBuffer();
                $this->analyzeInputBuffer();
            } catch (ExitException) {
                break;
            } catch (Throwable $e) {
                $this->inputBuffer = [];
                $this->io->writeln($this->style->red($e->getMessage()));
                $this->io->writeln($e->getTraceAsString());
            }
        }

        $this->io->writeln($this->style->yellow('Bye!'));
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

        /** @psalm-suppress RedundantCondition */
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
        if ($this->inputBuffer === []) {
            return;
        }

        $rawInput = implode(PHP_EOL, $this->inputBuffer);
        if (!$this->compilerFacade->hasBalancedParentheses($rawInput)) {
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
        } catch (UnfinishedParserException) {
            // The input is valid but more input is missing to finish the parsing.
        } catch (CompiledCodeIsMalformedException $e) {
            if ($e->getPrevious() instanceof Throwable) {
                $e = $e->getPrevious();
            }

            $exceptionClass = array_reverse(explode('\\', $e::class))[0];
            $this->io->writeln(
                sprintf(
                    '%s: %s',
                    $this->style->red($exceptionClass),
                    $e->getMessage() !== '' ? $e->getMessage() : '*no message*',
                ),
            );
            $this->addHistory($fullInput);
            $this->inputBuffer = [];
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
