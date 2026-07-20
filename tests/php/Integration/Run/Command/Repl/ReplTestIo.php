<?php

declare(strict_types=1);

namespace PhelTest\Integration\Run\Command\Repl;

use Phel\Run\Domain\Repl\ReplCommandIoInterface;
use Phel\Run\Domain\Repl\ReplErrorFormatter;
use Phel\Shared\ColorStyle;
use Phel\Shared\Exceptions\AbstractLocatedException;
use Phel\Shared\Exceptions\ExceptionPrinterInterface;
use Phel\Shared\Exceptions\Hint\ArgumentCountHint;
use Phel\Shared\Exceptions\Hint\ExceptionHintResolver;
use Phel\Shared\Exceptions\Hint\NotCallableHint;
use Phel\Shared\Exceptions\Hint\UndefinedSymbolHint;
use Phel\Shared\Parser\ReadModel\CodeSnippet;
use Throwable;

use function array_slice;
use function count;

final class ReplTestIo implements ReplCommandIoInterface
{
    /** @var list<string> */
    private array $outputs = [];

    /** @var list<InputLine> */
    private array $inputs = [];

    private int $currentIndex = 0;

    private readonly ReplErrorFormatter $errorFormatter;

    public function __construct(
        private readonly ExceptionPrinterInterface $exceptionPrinter,
        ?ReplErrorFormatter $errorFormatter = null,
    ) {
        $this->errorFormatter = $errorFormatter ?? new ReplErrorFormatter(
            new ExceptionHintResolver([
                new NotCallableHint(),
                new ArgumentCountHint(),
                new UndefinedSymbolHint(),
            ]),
            $exceptionPrinter,
            ColorStyle::noStyles(),
        );
    }

    public function readHistory(): void {}

    public function addHistory(string $line): void {}

    public function readline(?string $prompt = null): ?string
    {
        if ($this->currentIndex < count($this->inputs)) {
            $inputLine = $this->inputs[$this->currentIndex];
            $this->writeln($inputLine->__toString());
            ++$this->currentIndex;

            if ($inputLine->isCtrlD()) {
                return null;
            }

            return $inputLine->getContent();
        }

        return null;
    }

    public function writeStackTrace(Throwable $e): void
    {
        $this->write($this->exceptionPrinter->getStackTraceString($e));
    }

    public function writeReplError(Throwable $e): void
    {
        $this->write($this->errorFormatter->render($e));
    }

    public function writeLocatedException(AbstractLocatedException $e, CodeSnippet $codeSnippet): void
    {
        $this->write($this->exceptionPrinter->getExceptionString($e, $codeSnippet));
    }

    public function write(string $string = ''): void
    {
        $this->outputs[] = $string;
    }

    /**
     * Terminates the line, like the real IO does. Without this the captured
     * transcript runs an evaluated result straight into the next prompt
     * (`3user:2> *1`), which is why the `.test` fixtures could never match.
     */
    public function writeln(string $string = ''): void
    {
        $this->outputs[] = $string . PHP_EOL;
    }

    public function setInputs(InputLine ...$inputs): void
    {
        $this->inputs = $inputs;
        $this->currentIndex = 0;
    }

    /**
     * @return list<string>
     */
    public function getOutputs(): array
    {
        return array_slice($this->outputs, 2, -1);
    }

    public function getOutputString(): string
    {
        return implode('', $this->getOutputs()) . PHP_EOL;
    }

    /**
     * @return list<string>
     */
    public function getRawOutputs(): array
    {
        return $this->outputs;
    }

    /**
     * Raw outputs with the line terminator stripped, for assertions that care
     * about the logical line and not its framing. Prefer this over
     * {@see self::getRawOutputs()} when matching a whole entry, so the
     * assertion does not have to spell out `. PHP_EOL`.
     *
     * @return list<string>
     */
    public function getOutputLines(): array
    {
        return array_map(
            static fn(string $output): string => rtrim($output, PHP_EOL),
            $this->outputs,
        );
    }

    public function isBracketedPasteSupported(): bool
    {
        return false;
    }
}
