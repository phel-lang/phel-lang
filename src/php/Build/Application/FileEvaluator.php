<?php

declare(strict_types=1);

namespace Phel\Build\Application;

use ParseError;
use Phel\Build\Domain\Compile\CompiledFile;
use Phel\Build\Domain\Extractor\NamespaceExtractorInterface;
use Phel\Build\Infrastructure\Cache\CompiledCodeCache;
use Phel\Compiler\Infrastructure\CompileOptions;
use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;
use Phel\Shared\Facade\CompilerFacadeInterface;

use RuntimeException;

use Throwable;

use function sprintf;

final readonly class FileEvaluator
{
    public function __construct(
        private CompilerFacadeInterface $compilerFacade,
        private NamespaceExtractorInterface $namespaceExtractor,
        private ?CompiledCodeCache $compiledCodeCache = null,
    ) {
    }

    public function evalFile(string $src): CompiledFile
    {
        $code = file_get_contents($src);
        if ($code === false) {
            throw new RuntimeException(sprintf('Unable to read file "%s".', $src));
        }

        // Get namespace info (uses namespace cache if available)
        $namespaceInfo = $this->namespaceExtractor->getNamespaceFromFile($src);
        $namespace = $namespaceInfo->getNamespace();

        // Check compiled code cache
        if ($this->compiledCodeCache instanceof CompiledCodeCache) {
            $sourceHash = md5($code);
            $cachedPath = $this->compiledCodeCache->get($namespace, $sourceHash);

            if ($cachedPath !== null) {
                // Cache hit - ensure GlobalEnvironment is initialized then require
                if (!GlobalEnvironmentSingleton::isInitialized()) {
                    GlobalEnvironmentSingleton::initializeNew();
                }

                try {
                    /** @psalm-suppress UnresolvableInclude */
                    require $cachedPath;

                    return new CompiledFile($src, $cachedPath, $namespace);
                } catch (ParseError) {
                    // Parse errors indicate corrupt cache file - invalidate and recompile
                    $this->compiledCodeCache->invalidate($namespace);
                } catch (Throwable $e) {
                    // Other exceptions are user code errors - invalidate cache but re-throw
                    $this->compiledCodeCache->invalidate($namespace);
                    throw $e;
                }
            }

            // Cache miss - compile for cache (uses statement emit mode)
            $options = (new CompileOptions())
                ->setSource($src)
                ->setIsEnabledSourceMaps(false);

            $result = $this->compilerFacade->compileForCache($code, $options);
            $this->compiledCodeCache->put($namespace, $sourceHash, $result->getPhpCode());

            // Execute the cached code to register definitions in GlobalEnvironment
            $cachedPath = $this->compiledCodeCache->getCompiledPath($namespace);
            /** @psalm-suppress UnresolvableInclude */
            require $cachedPath;

            return new CompiledFile($src, $cachedPath, $namespace);
        }

        // No cache - use original behavior
        $options = (new CompileOptions())
            ->setSource($src)
            ->setIsEnabledSourceMaps(true);

        $this->compilerFacade->eval($code, $options);

        return new CompiledFile($src, '', $namespace);
    }
}
