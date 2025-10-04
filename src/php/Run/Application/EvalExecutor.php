<?php

declare(strict_types=1);

namespace Phel\Run\Application;

use Phel\Compiler\CompilerFacadeInterface;
use Phel\Compiler\Domain\Evaluator\Exceptions\CompiledCodeIsMalformedException;
use Phel\Compiler\Domain\Exceptions\CompilerException;
use Phel\Compiler\Domain\Parser\Exceptions\UnfinishedParserException;
use Phel\Compiler\Infrastructure\CompileOptions;
use Phel\Printer\PrinterInterface;
use Phel\Run\Domain\ParenthesesCheckerInterface;
use Phel\Run\Domain\Repl\ColorStyleInterface;
use Phel\Run\Domain\Repl\ReplCommandIoInterface;
use Throwable;

use function array_reverse;
use function explode;
use function sprintf;

final readonly class EvalExecutor
{
    public function __construct(
        private ReplCommandIoInterface $io,
        private ColorStyleInterface $style,
        private PrinterInterface $printer,
        private CompilerFacadeInterface $compilerFacade,
        private ParenthesesCheckerInterface $parenthesesChecker,
    ) {
    }

    public function execute(string $input): bool
    {
        if ($input === '') {
            return true;
        }

        if (!$this->parenthesesChecker->hasBalancedParentheses($input)) {
            $this->io->writeln($this->style->red('Unbalanced parentheses.'));

            return false;
        }

        $options = (new CompileOptions())->setStartingLine(1);

        try {
            $result = $this->compilerFacade->eval($input, $options);
            $this->io->writeln($this->printer->print($result));

            return true;
        } catch (UnfinishedParserException $e) {
            $this->io->writeLocatedException($e, $e->getCodeSnippet());

            return false;
        } catch (CompiledCodeIsMalformedException $e) {
            if ($e->getPrevious() instanceof Throwable) {
                $e = $e->getPrevious();
            }

            $exceptionClass = array_reverse(explode('\\', $e::class))[0];
            $this->io->writeln(sprintf(
                '%s: %s',
                $this->style->red($exceptionClass),
                $e->getMessage() !== '' ? $e->getMessage() : '*no message*',
            ));

            return false;
        } catch (CompilerException $e) {
            $this->io->writeLocatedException($e->getNestedException(), $e->getCodeSnippet());

            return false;
        } catch (Throwable $e) {
            $this->io->writeStackTrace($e);

            return false;
        }
    }
}
