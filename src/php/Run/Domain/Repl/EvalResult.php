<?php

declare(strict_types=1);

namespace Phel\Run\Domain\Repl;

use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironmentInterface;
use Phel\Compiler\Domain\Evaluator\Exceptions\CompiledCodeIsMalformedException;
use Phel\Compiler\Domain\Exceptions\CompilerException;
use Phel\Compiler\Domain\Parser\Exceptions\UnfinishedParserException;
use Phel\Compiler\Infrastructure\CompileOptions;
use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;
use Phel\Shared\Facade\CompilerFacadeInterface;
use Throwable;

final readonly class EvalResult
{
    private function __construct(
        public bool $success,
        public bool $incomplete,
        public mixed $value,
        public ?EvalError $error,
        public string $output,
    ) {}

    public static function success(mixed $value, string $output = ''): self
    {
        return new self(success: true, incomplete: false, value: $value, error: null, output: $output);
    }

    public static function incomplete(string $output = ''): self
    {
        return new self(success: false, incomplete: true, value: null, error: null, output: $output);
    }

    public static function failure(EvalError $error, string $output = ''): self
    {
        return new self(success: false, incomplete: false, value: null, error: $error, output: $output);
    }

    public static function fromEval(
        CompilerFacadeInterface $compilerFacade,
        string $phelCode,
        CompileOptions $compileOptions = new CompileOptions(),
    ): self {
        $env = self::captureEnvironment();
        $snapshot = $env?->snapshot();

        ob_start();

        try {
            $result = $compilerFacade->eval($phelCode, $compileOptions);
            $output = (string) ob_get_clean();

            return self::success($result, $output);
        } catch (UnfinishedParserException) {
            $output = (string) ob_get_clean();
            self::restoreIfNeeded($env, $snapshot);

            return self::incomplete($output);
        } catch (CompilerException $e) {
            $output = (string) ob_get_clean();
            self::restoreIfNeeded($env, $snapshot);

            $nested = $e->getNestedException();
            $snippet = $e->getCodeSnippet();
            $startLoc = $nested->getStartLocation();
            $endLoc = $nested->getEndLocation();

            return self::failure(new EvalError(
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
                frames: self::extractFrames($nested),
            ), $output);
        } catch (CompiledCodeIsMalformedException $e) {
            $output = (string) ob_get_clean();
            self::restoreIfNeeded($env, $snapshot);

            $prev = $e->getPrevious() instanceof Throwable ? $e->getPrevious() : $e;

            return self::failure(new EvalError(
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
                frames: self::extractFrames($prev),
            ), $output);
        } catch (Throwable $e) {
            $output = (string) ob_get_clean();
            self::restoreIfNeeded($env, $snapshot);

            return self::failure(new EvalError(
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
                frames: self::extractFrames($e),
            ), $output);
        }
    }

    /**
     * @return list<StackFrame>
     */
    private static function extractFrames(Throwable $e): array
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

    private static function captureEnvironment(): ?GlobalEnvironmentInterface
    {
        return GlobalEnvironmentSingleton::isInitialized()
            ? GlobalEnvironmentSingleton::getInstance()
            : null;
    }

    private static function restoreIfNeeded(?GlobalEnvironmentInterface $env, ?array $snapshot): void
    {
        if ($env instanceof GlobalEnvironmentInterface && $snapshot !== null) {
            $env->restore($snapshot);
        }
    }
}
