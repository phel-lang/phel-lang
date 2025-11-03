<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Evaluator;

use Phel\Compiler\Domain\Evaluator\Exceptions\CompiledCodeIsMalformedException;
use Phel\Compiler\Domain\Evaluator\Exceptions\FileException;
use Phel\Compiler\Infrastructure\CompiledCodeCache;
use Phel\Run\Infrastructure\Service\DebugLineTap;
use Throwable;

use function function_exists;

final readonly class RequireEvaluator implements EvaluatorInterface
{
    public function __construct(
        private CompiledCodeCache $compiledCodeCache,
    ) {
    }

    /**
     * Evaluates the code and returns the evaluated value.
     *
     * @throws CompiledCodeIsMalformedException
     * @throws FileException
     */
    public function eval(string $code): mixed
    {
        // Inject declare(ticks=1) if debug is enabled
        $phpCode = DebugLineTap::isEnabled()
            ? "<?php\ndeclare(ticks=1);\n" . $code
            : "<?php\n" . $code;

        try {
            // Try to get from cache first
            $cachedFile = $this->compiledCodeCache->get($phpCode);
            if ($cachedFile !== null && file_exists($cachedFile)) {
                if (function_exists('opcache_compile_file')) {
                    @opcache_compile_file($cachedFile);
                }

                /** @psalm-suppress UnresolvableInclude */
                return require $cachedFile;
            }

            // Cache miss: store in cache and evaluate
            $filename = $this->compiledCodeCache->store($phpCode);

            if (function_exists('opcache_compile_file')) {
                @opcache_compile_file($filename);
            }

            /** @psalm-suppress UnresolvableInclude */
            return require $filename;
        } catch (Throwable $throwable) {
            throw CompiledCodeIsMalformedException::fromThrowable($throwable);
        }
    }
}
