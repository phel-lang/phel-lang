<?php

declare(strict_types=1);

namespace Phel\Run\Application;

use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironmentInterface;
use Phel\Compiler\Domain\Parser\Exceptions\UnfinishedParserException;
use Phel\Lang\Symbol;
use Phel\Shared\CompileOptions;
use Phel\Shared\Eval\EvalError;
use Phel\Shared\Eval\EvalResult;
use Phel\Shared\Eval\StackFrame;
use Phel\Shared\Exceptions\CompiledCodeIsMalformedException;
use Phel\Shared\Exceptions\CompilerException;
use Phel\Shared\Facade\CompilerFacadeInterface;
use Throwable;

use function array_reverse;
use function explode;
use function ob_get_clean;
use function ob_start;

final readonly class StructuredEvaluator
{
    public function __construct(
        private CompilerFacadeInterface $compilerFacade,
    ) {}

    public function eval(string $phelCode, CompileOptions $compileOptions): EvalResult
    {
        $env = $this->captureEnvironment();
        $snapshot = $env?->snapshot();

        ob_start();

        try {
            $result = $this->compilerFacade->eval($phelCode, $compileOptions);
            $output = (string) ob_get_clean();

            return EvalResult::success($result, $output);
        } catch (UnfinishedParserException) {
            $output = (string) ob_get_clean();
            $this->restoreIfNeeded($env, $snapshot);

            return EvalResult::incomplete($output);
        } catch (CompilerException $e) {
            $output = (string) ob_get_clean();
            $this->restoreIfNeeded($env, $snapshot);

            $nested = $e->getNestedException();
            $snippet = $e->getCodeSnippet();
            $startLoc = $nested->getStartLocation();
            $endLoc = $nested->getEndLocation();

            return EvalResult::failure(new EvalError(
                exceptionClass: array_reverse(explode('\\', $nested::class))[0],
                message: $nested->getMessage(),
                errorCode: $nested->getErrorCode()?->value,
                file: $startLoc?->getFile(),
                line: $startLoc?->getLine(),
                column: $startLoc?->getColumn(),
                endLine: $endLoc?->getLine(),
                endColumn: $endLoc?->getColumn(),
                codeSnippet: $snippet->getCode(),
                stackTrace: $nested->getTraceAsString(),
                phase: 'compile',
                frames: $this->extractFrames($nested),
            ), $output);
        } catch (CompiledCodeIsMalformedException $e) {
            $output = (string) ob_get_clean();
            $this->restoreIfNeeded($env, $snapshot);

            $prev = $e->getPrevious() instanceof Throwable ? $e->getPrevious() : $e;

            return EvalResult::failure($this->errorFromThrowable($prev, 'eval'), $output);
        } catch (Throwable $e) {
            $output = (string) ob_get_clean();
            $this->restoreIfNeeded($env, $snapshot);

            return EvalResult::failure($this->errorFromThrowable($e, 'runtime'), $output);
        }
    }

    /**
     * Builds an `EvalError` from a non-located throwable (eval/runtime phases).
     * Located compiler failures are handled inline because they additionally
     * carry source coordinates and a code snippet.
     */
    private function errorFromThrowable(Throwable $t, string $phase): EvalError
    {
        return new EvalError(
            exceptionClass: array_reverse(explode('\\', $t::class))[0],
            message: $t->getMessage(),
            errorCode: null,
            file: $t->getFile(),
            line: $t->getLine(),
            column: null,
            endLine: null,
            endColumn: null,
            codeSnippet: null,
            stackTrace: $t->getTraceAsString(),
            phase: $phase,
            frames: $this->extractFrames($t),
        );
    }

    /**
     * @return list<StackFrame>
     */
    private function extractFrames(Throwable $e): array
    {
        $frames = [];

        /** @var array{file?: string, line?: int, class?: string, function?: string} $entry */
        foreach ($e->getTrace() as $entry) {
            $file = $entry['file'] ?? null;
            $line = $entry['line'] ?? null;
            if ($file === null) {
                continue;
            }

            if ($line === null) {
                continue;
            }

            $frames[] = new StackFrame(
                file: $file,
                line: $line,
                class: $entry['class'] ?? null,
                function: $entry['function'] ?? null,
            );
        }

        return $frames;
    }

    private function captureEnvironment(): ?GlobalEnvironmentInterface
    {
        return $this->compilerFacade->isGlobalEnvironmentInitialized()
            ? $this->compilerFacade->getGlobalEnvironment()
            : null;
    }

    /**
     * @param array{
     *     ns: string,
     *     definitions: array<string, array<string, bool>>,
     *     refers: array<string, array<string, Symbol>>,
     *     requireAliases: array<string, array<string, Symbol>>,
     *     useAliases: array<string, array<string, Symbol>>,
     *     interfaces: array<string, array<string, Symbol>>,
     * }|null $snapshot
     */
    private function restoreIfNeeded(?GlobalEnvironmentInterface $env, ?array $snapshot): void
    {
        if ($env instanceof GlobalEnvironmentInterface && $snapshot !== null) {
            $env->restore($snapshot);
        }
    }
}
