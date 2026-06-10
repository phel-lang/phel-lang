<?php

declare(strict_types=1);

namespace Phel\Run\Application;

use Phel\Shared\CompileOptions;
use Phel\Shared\Exceptions\CompilerException;
use Phel\Shared\Facade\CompilerFacadeInterface;
use Throwable;

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
            $result = $this->compilerFacade->compile($source, $options);
            $stdout($result->getPhpCode());
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
}
