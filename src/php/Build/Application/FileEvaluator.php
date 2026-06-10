<?php

declare(strict_types=1);

namespace Phel\Build\Application;

use ParseError;
use Phel\Build\Domain\Cache\CompiledCodeCacheInterface;
use Phel\Build\Domain\Cache\DependencyTrackerInterface;
use Phel\Build\Domain\Compile\CompiledFile;
use Phel\Build\Domain\Extractor\FirstFormExtractor;
use Phel\Build\Domain\Extractor\NamespaceExtractorInterface;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Shared\CompileOptions;
use Phel\Shared\Facade\CompilerFacadeInterface;
use Phel\Shared\Parser\Node\NodeInterface;
use Phel\Shared\Parser\Node\TriviaNodeInterface;
use RuntimeException;
use Throwable;

use function sprintf;

final class FileEvaluator
{
    /**
     * Per-process record of namespaces whose env data was already restored
     * from cache. Restoring re-iterates every refer/alias of a namespace, so
     * repeating it for `phel.core` or other large namespaces during a single
     * `bin/phel test` run is pure overhead.
     *
     * @var array<string, true>
     */
    private static array $restoredNamespaces = [];

    public function __construct(
        private readonly CompilerFacadeInterface $compilerFacade,
        private readonly NamespaceExtractorInterface $namespaceExtractor,
        private readonly ?CompiledCodeCacheInterface $compiledCodeCache = null,
        private readonly FirstFormExtractor $firstFormExtractor = new FirstFormExtractor(),
        private readonly ?DependencyTrackerInterface $dependencyTracker = null,
        private readonly int $optimizationLevel = CompileOptions::DEFAULT_OPTIMIZATION_LEVEL,
    ) {}

    /**
     * Reset the per-process namespace restoration cache. Tests should call
     * this between scenarios so static state does not leak across runs.
     */
    public static function resetState(): void
    {
        self::$restoredNamespaces = [];
    }

    public function evalFile(string $src): CompiledFile
    {
        $code = @file_get_contents($src);
        if ($code === false) {
            $error = error_get_last();
            throw new RuntimeException(sprintf(
                'Unable to read file "%s": %s',
                $src,
                $error['message'] ?? 'unknown error',
            ));
        }

        $namespaceInfo = $this->namespaceExtractor->getNamespaceFromFile($src);
        $namespace = $namespaceInfo->getNamespace();

        if ($this->compiledCodeCache instanceof CompiledCodeCacheInterface) {
            $sourceHash = $this->sourceHash($code);
            $cachedPath = $this->compiledCodeCache->get($src, $sourceHash);

            if ($cachedPath !== null) {
                $this->compilerFacade->initializeGlobalEnvironment();
                try {
                    if (!isset(self::$restoredNamespaces[$namespace])) {
                        $envData = $this->compiledCodeCache->getEnvironment($namespace);

                        if ($envData !== null) {
                            $this->compilerFacade->restoreNamespaceEnvironmentData($namespace, $envData);
                        } else {
                            $this->analyzeNsForm($code, $src);
                        }

                        self::$restoredNamespaces[$namespace] = true;
                    }

                    /** @psalm-suppress UnresolvableInclude */
                    require $cachedPath;

                    return new CompiledFile($src, $cachedPath, $namespace);
                } catch (ParseError) {
                    $this->compiledCodeCache->invalidate($src);
                } catch (Throwable $e) {
                    $this->compiledCodeCache->invalidate($src);
                    throw $e;
                }
            } elseif ($this->dependencyTracker instanceof DependencyTrackerInterface
                && $this->compiledCodeCache->has($src)
            ) {
                // Stale cache entry — source changed. Cascade invalidation to dependents.
                $this->dependencyTracker->invalidateDependentsOf($namespace, $this->compiledCodeCache);
            }

            // Fresh compile: register dependencies so future runs can cascade
            // invalidations. Skipped on cache hit since the previous fresh
            // compile already registered them.
            if ($this->dependencyTracker instanceof DependencyTrackerInterface) {
                $this->dependencyTracker->registerDependencies(
                    $src,
                    $namespace,
                    $namespaceInfo->getDependencies(),
                );
            }

            $options = new CompileOptions()
                ->setSource($src)
                ->setIsEnabledSourceMaps(false)
                ->setOptimizationLevel($this->optimizationLevel);

            $result = $this->compilerFacade->compileForCache($code, $options);
            $this->compiledCodeCache->put($src, $namespace, $sourceHash, $result->getPhpCode());

            $envData = $this->compilerFacade->getNamespaceEnvironmentData($namespace);
            $this->compiledCodeCache->putEnvironment($namespace, $envData);

            return new CompiledFile(
                $src,
                $this->compiledCodeCache->getCompiledPath($src, $namespace),
                $namespace,
            );
        }

        $options = new CompileOptions()
            ->setSource($src)
            ->setIsEnabledSourceMaps(true)
            ->setOptimizationLevel($this->optimizationLevel);

        $this->compilerFacade->eval($code, $options);

        return new CompiledFile($src, '', $namespace);
    }

    /**
     * Cache key for the compiled-code cache. The optimization level is mixed
     * in so changing it invalidates entries compiled at another level; level 0
     * keeps the historical plain `md5` so existing caches stay warm.
     */
    private function sourceHash(string $code): string
    {
        return $this->optimizationLevel > 0
            ? md5($code . '|O' . $this->optimizationLevel)
            : md5($code);
    }

    private function analyzeNsForm(string $code, string $src): void
    {
        try {
            $nsFormText = $this->firstFormExtractor->extract($code);
            $tokenStream = $this->compilerFacade->lexString($nsFormText, $src);

            while (true) {
                $parseTree = $this->compilerFacade->parseNext($tokenStream);

                if (!$parseTree instanceof NodeInterface) {
                    break;
                }

                if ($parseTree instanceof TriviaNodeInterface) {
                    continue;
                }

                $readerResult = $this->compilerFacade->read($parseTree);
                $this->compilerFacade->analyze(
                    $readerResult->getAst(),
                    NodeEnvironment::empty(),
                );

                break;
            }
        } catch (Throwable) {
            // Analysis failure is non-fatal
        }
    }
}
