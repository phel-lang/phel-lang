<?php

declare(strict_types=1);

namespace Phel\Run\Application;

use Phel\Shared\CompileOptions;
use Phel\Shared\Exceptions\CompilerException;
use Phel\Shared\Facade\CompilerFacadeInterface;
use Throwable;

use function sprintf;

/**
 * Compiles a Phel snippet to PHP without evaluating it.
 *
 * Powers the `phel compile` CLI command (issue #2043). The compiled
 * PHP is written to `$stdout` on success; any error is written to
 * `$stderr` and signalled via the return value.
 */
final readonly class CompileExecutor
{
    public function __construct(
        private CompilerFacadeInterface $compilerFacade,
        private int $optimizationLevel = CompileOptions::DEFAULT_OPTIMIZATION_LEVEL,
    ) {}

    /**
     * @param callable(string): void $stdout
     * @param callable(string): void $stderr
     *
     * @return bool `true` on successful compile
     */
    public function execute(string $source, callable $stdout, callable $stderr): bool
    {
        if ($source === '') {
            return true;
        }

        if (!$this->compilerFacade->hasBalancedParentheses($source)) {
            $stderr("Unbalanced parentheses.\n");
            return false;
        }

        try {
            $options = new CompileOptions()
                ->setEmitOnly(true)
                ->setOptimizationLevel($this->optimizationLevel);
            $phpCode = $this->compilerFacade->compile($source, $options)->getPhpCode();

            if (trim($phpCode) === '') {
                $stderr($this->emptyOutputNote($source));
                return true;
            }

            $stdout($phpCode);
            return true;
        } catch (CompilerException $e) {
            $nested = $e->getNestedException();
            $stderr($nested->getMessage() . "\n");
            return false;
        } catch (Throwable $e) {
            $stderr($e->getMessage() . "\n");
            return false;
        }
    }

    /**
     * A pure value in statement context emits no PHP (e.g. `(+ 1 2)` folds to
     * `3`, which is then discarded). Re-compile in expression context — without
     * evaluating — to recover the value so the note can name it instead of
     * leaving the user staring at empty output.
     */
    private function emptyOutputNote(string $source): string
    {
        $value = $this->compileValueExpression($source);
        $reason = 'a top-level expression with no binding or side effect emits no PHP';

        if ($value === '') {
            return sprintf("; no PHP emitted — %s.\n", $reason);
        }

        return sprintf("; no PHP emitted — this compiles to the value `%s`, which is discarded (%s).\n", $value, $reason);
    }

    private function compileValueExpression(string $source): string
    {
        try {
            $options = new CompileOptions()
                ->setEmitOnly(true)
                ->setEmitAsExpression(true)
                ->setOptimizationLevel($this->optimizationLevel);

            return trim($this->compilerFacade->compile($source, $options)->getPhpCode());
        } catch (Throwable) {
            return '';
        }
    }
}
