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
use Phel\Lang\TypeInterface;
use Phel\Shared\CompileOptions;
use Phel\Shared\Facade\CompilerFacadeInterface;
use Phel\Shared\Parser\Node\NodeInterface;
use Phel\Shared\Parser\Node\TriviaNodeInterface;
use Phel\Shared\SourceMap\BuiltFilePreamble;
use RuntimeException;
use Throwable;

use function is_file;
use function preg_replace;
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

        $precompiledSibling = $this->precompiledSiblingPath($src);
        if ($precompiledSibling !== null) {
            $this->compilerFacade->initializeGlobalEnvironment();
            /** @psalm-suppress UnresolvableInclude */
            require $precompiledSibling;

            return new CompiledFile($src, $precompiledSibling, $namespace);
        }

        if ($this->compiledCodeCache instanceof CompiledCodeCacheInterface) {
            $sourceHash = $this->sourceHash($code);
            $cachedPath = $this->compiledCodeCache->get($src, $sourceHash);

            if ($cachedPath !== null) {
                $this->compilerFacade->initializeGlobalEnvironment();
                try {
                    if (!isset(self::$restoredNamespaces[$namespace])) {
                        $envData = $this->compiledCodeCache->getEnvironment($namespace);

                        if ($envData !== null) {
                            /** @var array{refers: array<string, array{ns: ?string, name: string}>, require_aliases: array<string, array{ns: ?string, name: string}>, use_aliases: array<string, array{ns: ?string, name: string}>} $envData */
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
                ->setIsEnabledSourceMaps(true)
                ->setOptimizationLevel($this->optimizationLevel);

            $result = $this->compilerFacade->compileForCache($code, $options);
            $this->compiledCodeCache->put($src, $namespace, $sourceHash, $result->getCodeWithSourceMap());

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

    /**
     * Returns the path of a precompiled `.php` sibling shipped next to a
     * `.phel`/`.cljc` source (the stdlib bundled in the PHAR), or null when
     * none applies.
     *
     * When present, the sibling is `require`d directly, skipping the
     * lex/parse/analyze/emit pipeline and the compiled-code cache entirely:
     * running the compiled file registers every definition (with macro meta)
     * in the runtime registry, which is all the analyzer needs to resolve
     * those symbols when it later compiles user code. The file must carry the
     * build preamble so a hand-written PHP file that merely happens to sit
     * next to a Phel source is never executed.
     */
    private function precompiledSiblingPath(string $src): ?string
    {
        $sibling = preg_replace('/\.(phel|cljc)$/i', '.php', $src);
        if ($sibling === null || $sibling === $src || !is_file($sibling)) {
            return null;
        }

        $head = @file_get_contents($sibling, length: 64);
        if ($head === false || !BuiltFilePreamble::isPresent($head)) {
            return null;
        }

        return $sibling;
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
                /** @var bool|float|int|string|TypeInterface|null $ast */
                $ast = $readerResult->getAst();
                $this->compilerFacade->analyze(
                    $ast,
                    NodeEnvironment::empty(),
                );

                break;
            }
        } catch (Throwable) {
            // Analysis failure is non-fatal
        }
    }
}
