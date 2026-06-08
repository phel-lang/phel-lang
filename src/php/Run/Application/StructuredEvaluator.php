<?php

declare(strict_types=1);

namespace Phel\Run\Application;

use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironmentInterface;
use Phel\Compiler\Domain\Parser\Exceptions\UnfinishedParserException;
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

            return EvalResult::failure(new EvalError(
                exceptionClass: array_reverse(explode('\\', $prev::class))[0],
                message: $prev->getMessage(),
                errorCode: null,
                file: $prev->getFile(),
                line: $prev->getLine(),
                column: null,
                endLine: null,
                endColumn: null,
                codeSnippet: null,
                stackTrace: $prev->getTraceAsString(),
                phase: 'eval',
                frames: $this->extractFrames($prev),
            ), $output);
        } catch (Throwable $e) {
            $output = (string) ob_get_clean();
            $this->restoreIfNeeded($env, $snapshot);

            return EvalResult::failure(new EvalError(
                exceptionClass: array_reverse(explode('\\', $e::class))[0],
                message: $e->getMessage(),
                errorCode: null,
                file: $e->getFile(),
                line: $e->getLine(),
                column: null,
                endLine: null,
                endColumn: null,
                codeSnippet: null,
                stackTrace: $e->getTraceAsString(),
                phase: 'runtime',
                frames: $this->extractFrames($e),
            ), $output);
        }
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
     * @param array<string, mixed>|null $snapshot
     */
    private function restoreIfNeeded(?GlobalEnvironmentInterface $env, ?array $snapshot): void
    {
        if ($env instanceof GlobalEnvironmentInterface && $snapshot !== null) {
            $env->restore($snapshot);
        }
    }
}
