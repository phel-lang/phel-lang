<?php

declare(strict_types=1);

namespace Phel\Build\Application;

use ParseError;
use Phel\Build\Domain\Compile\CompiledFile;
use Phel\Build\Domain\Extractor\NamespaceExtractorInterface;
use Phel\Build\Infrastructure\Cache\CompiledCodeCache;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Parser\ParserNode\NodeInterface;
use Phel\Compiler\Domain\Parser\ParserNode\TriviaNodeInterface;
use Phel\Compiler\Infrastructure\CompileOptions;
use Phel\Shared\Facade\CompilerFacadeInterface;

use RuntimeException;

use Throwable;

use function sprintf;
use function strlen;
use function substr;

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
        $code = @file_get_contents($src);
        if ($code === false) {
            $error = error_get_last();
            throw new RuntimeException(sprintf(
                'Unable to read file "%s": %s',
                $src,
                $error['message'] ?? 'unknown error',
            ));
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
                $this->compilerFacade->initializeGlobalEnvironment();

                try {
                    // Analyze the ns form to restore refers/aliases in GlobalEnvironment,
                    // which are only registered as analyzer side effects during compilation.
                    $this->analyzeNsForm($code, $src);

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

    /**
     * Analyzes the ns form of a source file to register refers, require aliases,
     * and use aliases in the GlobalEnvironment. This is needed on cache hits
     * because these are analyzer side effects that aren't persisted in the cache.
     */
    private function analyzeNsForm(string $code, string $src): void
    {
        try {
            // Only lex the ns form, not the entire file, to avoid memory
            // exhaustion on large files like phel\core.
            $nsFormText = $this->extractFirstFormText($code);
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

                // Only need the first non-trivia form (the ns declaration)
                break;
            }
        } catch (Throwable) {
            // Analysis failure is non-fatal — the cached PHP will still execute.
            // The only consequence is missing refers/aliases in the GlobalEnvironment.
        }
    }

    /**
     * Extracts the text of the first top-level form (the ns declaration)
     * from source code by matching balanced parentheses, skipping strings
     * and comments. This avoids lexing the entire file content.
     */
    private function extractFirstFormText(string $code): string
    {
        $len = strlen($code);
        $depth = 0;
        $inString = false;

        for ($i = 0; $i < $len; ++$i) {
            $c = $code[$i];

            if ($inString) {
                if ($c === '\\') {
                    ++$i;
                } elseif ($c === '"') {
                    $inString = false;
                }

                continue;
            }

            if ($c === '#') {
                $newline = strpos($code, "\n", $i);
                $i = $newline === false ? $len : $newline;

                continue;
            }

            if ($c === '"') {
                $inString = true;

                continue;
            }

            if ($c === '(') {
                ++$depth;
            } elseif ($c === ')') {
                --$depth;
                if ($depth === 0) {
                    return substr($code, 0, $i + 1);
                }
            }
        }

        return $code;
    }
}
