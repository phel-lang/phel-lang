<?php

declare(strict_types=1);

namespace Phel\Build\Application;

use ParseError;
use Phel\Build\Domain\Cache\DependencyTrackerInterface;
use Phel\Build\Domain\Compile\CompiledFile;
use Phel\Build\Domain\Extractor\FirstFormExtractor;
use Phel\Build\Domain\Extractor\NamespaceExtractorInterface;
use Phel\Build\Infrastructure\Cache\CompiledCodeCache;
use Phel\Build\Infrastructure\Cache\DependencyTracker;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Parser\ParserNode\NodeInterface;
use Phel\Compiler\Domain\Parser\ParserNode\TriviaNodeInterface;
use Phel\Shared\CompileOptions;
use Phel\Shared\Facade\CompilerFacadeInterface;
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
        private readonly ?CompiledCodeCache $compiledCodeCache = null,
        private readonly FirstFormExtractor $firstFormExtractor = new FirstFormExtractor(),
        private readonly ?DependencyTrackerInterface $dependencyTracker = null,
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

        if ($this->compiledCodeCache instanceof CompiledCodeCache) {
            $sourceHash = md5($code);
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
            } elseif ($this->dependencyTracker instanceof DependencyTracker
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
                ->setIsEnabledSourceMaps(false);

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
            ->setIsEnabledSourceMaps(true);

        $this->compilerFacade->eval($code, $options);

        return new CompiledFile($src, '', $namespace);
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
