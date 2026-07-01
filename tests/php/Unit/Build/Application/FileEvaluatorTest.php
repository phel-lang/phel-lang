<?php

declare(strict_types=1);

namespace PhelTest\Unit\Build\Application;

use Phel;
use Phel\Build\Application\FileEvaluator;
use Phel\Build\BuildFacade;
use Phel\Build\Domain\Cache\DependencyTrackerInterface;
use Phel\Build\Domain\Extractor\FirstFormExtractor;
use Phel\Build\Domain\Extractor\NamespaceExtractorInterface;
use Phel\Build\Infrastructure\Cache\CompiledCodeCache;
use Phel\Compiler\Domain\Emitter\EmitterResult;
use Phel\Lang\Registry;
use Phel\Shared\CompileOptions;
use Phel\Shared\Facade\CompilerFacadeInterface;
use Phel\Shared\NamespaceInformation;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

use function sprintf;

final class FileEvaluatorTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/phel-test-' . uniqid();
        mkdir($this->tempDir, 0755, true);
        FileEvaluator::resetState();
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tempDir);
        FileEvaluator::resetState();
    }

    public function test_eval_file_uses_cache_on_hit(): void
    {
        $sourceFile = $this->tempDir . '/test.phel';
        file_put_contents($sourceFile, '(ns test\\namespace)');
        $cacheDir = $this->tempDir . '/cache';
        $namespace = 'test\\namespace';
        $sourceHash = md5('(ns test\\namespace)');

        // Set up cache with valid entry
        $cache = new CompiledCodeCache($cacheDir);
        $cache->put($sourceFile, $namespace, $sourceHash, '$result = 42;');

        // Compiler should NOT be called when cache hits
        $compilerFacade = $this->createMock(CompilerFacadeInterface::class);
        $compilerFacade->expects($this->never())->method('compileForCache');
        $compilerFacade->expects($this->never())->method('eval');

        $namespaceExtractor = $this->createStub(NamespaceExtractorInterface::class);
        $namespaceExtractor->method('getNamespaceFromFile')->willReturn(
            new NamespaceInformation($sourceFile, $namespace, ['phel.core']),
        );

        $evaluator = new FileEvaluator($compilerFacade, $namespaceExtractor, $cache);
        $result = $evaluator->evalFile($sourceFile);

        self::assertSame($sourceFile, $result->getSourceFile());
        self::assertSame($namespace, $result->getNamespace());
        self::assertStringContainsString('test_namespace__', $result->getTargetFile());
        self::assertStringEndsWith('.php', $result->getTargetFile());
    }

    public function test_eval_file_uses_precompiled_sibling_when_present(): void
    {
        $sourceFile = $this->tempDir . '/test.phel';
        file_put_contents($sourceFile, '(ns test\\namespace)');
        $namespace = 'test\\namespace';

        // A precompiled `.php` sibling carrying the build preamble must be
        // required directly, bypassing the cache and the compiler entirely.
        $siblingFile = $this->tempDir . '/test.php';
        file_put_contents(
            $siblingFile,
            "<?php declare(strict_types=1);\n\$GLOBALS['phel_precompiled_sibling_ran'] = true;\n",
        );

        $compilerFacade = $this->createMock(CompilerFacadeInterface::class);
        $compilerFacade->expects($this->once())->method('initializeGlobalEnvironment');
        $compilerFacade->expects($this->never())->method('compileForCache');
        $compilerFacade->expects($this->never())->method('eval');

        $namespaceExtractor = $this->createStub(NamespaceExtractorInterface::class);
        $namespaceExtractor->method('getNamespaceFromFile')->willReturn(
            new NamespaceInformation($sourceFile, $namespace, ['phel.core']),
        );

        $evaluator = new FileEvaluator($compilerFacade, $namespaceExtractor);
        $result = $evaluator->evalFile($sourceFile);

        self::assertSame($sourceFile, $result->getSourceFile());
        self::assertSame($siblingFile, $result->getTargetFile());
        self::assertSame($namespace, $result->getNamespace());
        self::assertTrue($GLOBALS['phel_precompiled_sibling_ran'] ?? false);

        unset($GLOBALS['phel_precompiled_sibling_ran']);
    }

    public function test_re_eval_of_precompiled_sibling_keeps_forward_declared_def(): void
    {
        // #2673: a bundled primary forward-declares a def as null, then a
        // `require_once` secondary registers the real value. A second evalFile
        // must not re-run the primary and re-null the def (`map`/`seq`/`nil?`
        // in phel.core). This is the exact shape only the PHAR ships.
        $namespace = 'test\\precompiled_idempotent_2673';
        $defName = 'the-fn';

        $sourceFile = $this->tempDir . '/core.phel';
        file_put_contents($sourceFile, '(ns test\\precompiled_idempotent_2673)');

        // The secondary is pulled in with `require_once`, so it runs once per
        // process — mirroring a compiled `(load ...)` secondary.
        $this->writePrecompiledDef(
            $this->tempDir . '/core-secondary.php',
            $namespace,
            $defName,
            "static fn () => 'real'",
        );

        $this->writePrecompiledDef(
            $this->tempDir . '/core.php',
            $namespace,
            $defName,
            'null',
            "require_once __DIR__ . '/core-secondary.php';\n",
        );

        $compilerFacade = $this->createStub(CompilerFacadeInterface::class);
        $namespaceExtractor = $this->createStub(NamespaceExtractorInterface::class);
        $namespaceExtractor->method('getNamespaceFromFile')->willReturn(
            new NamespaceInformation($sourceFile, $namespace, ['phel.core']),
        );

        $evaluator = new FileEvaluator($compilerFacade, $namespaceExtractor);
        $evaluator->evalFile($sourceFile);
        $evaluator->evalFile($sourceFile);

        $def = Registry::getInstance()->getDefinition($namespace, $defName);
        self::assertNotNull($def, 'Re-evaluating a precompiled sibling must not null a forward-declared def');
        self::assertSame('real', $def());

        Registry::getInstance()->removeNamespace($namespace);
    }

    public function test_eval_file_ignores_precompiled_sibling_during_build(): void
    {
        $sourceFile = $this->tempDir . '/test.phel';
        file_put_contents($sourceFile, '(ns test\\namespace)');
        $namespace = 'test\\namespace';

        // A precompiled sibling exists, but `phel build` must compile the
        // source (and harvest its secondaries) rather than short-circuit to
        // the bundled artifact.
        file_put_contents(
            $this->tempDir . '/test.php',
            "<?php declare(strict_types=1);\n\$GLOBALS['phel_build_sibling_ran'] = true;\n",
        );

        $compilerFacade = $this->createMock(CompilerFacadeInterface::class);
        $compilerFacade->expects($this->once())
            ->method('compileForCache')
            ->willReturn(new EmitterResult(false, '$compiled = true;', '', ''));

        $namespaceExtractor = $this->createStub(NamespaceExtractorInterface::class);
        $namespaceExtractor->method('getNamespaceFromFile')->willReturn(
            new NamespaceInformation($sourceFile, $namespace, ['phel.core']),
        );

        $cache = new CompiledCodeCache($this->tempDir . '/cache');
        $evaluator = new FileEvaluator($compilerFacade, $namespaceExtractor, $cache);

        BuildFacade::enableBuildMode();
        try {
            $evaluator->evalFile($sourceFile);
        } finally {
            BuildFacade::disableBuildMode();
        }

        self::assertArrayNotHasKey('phel_build_sibling_ran', $GLOBALS);
    }

    public function test_eval_file_ignores_non_phel_php_sibling(): void
    {
        $sourceFile = $this->tempDir . '/test.phel';
        $sourceCode = '(ns test\\namespace)';
        file_put_contents($sourceFile, $sourceCode);
        $namespace = 'test\\namespace';

        // A hand-written PHP file next to a Phel source (no build preamble)
        // must NOT be treated as a precompiled artifact.
        file_put_contents($this->tempDir . '/test.php', "<?php\nthrow new \\RuntimeException('must not run');\n");

        $compilerFacade = $this->createMock(CompilerFacadeInterface::class);
        $compilerFacade->expects($this->once())->method('eval');

        $namespaceExtractor = $this->createStub(NamespaceExtractorInterface::class);
        $namespaceExtractor->method('getNamespaceFromFile')->willReturn(
            new NamespaceInformation($sourceFile, $namespace, ['phel.core']),
        );

        $evaluator = new FileEvaluator($compilerFacade, $namespaceExtractor);
        $result = $evaluator->evalFile($sourceFile);

        self::assertSame($namespace, $result->getNamespace());
        self::assertSame('', $result->getTargetFile());
    }

    public function test_eval_file_compiles_on_cache_miss(): void
    {
        $sourceFile = $this->tempDir . '/test.phel';
        file_put_contents($sourceFile, '(ns test\\namespace)');
        $cacheDir = $this->tempDir . '/cache';
        $namespace = 'test\\namespace';

        $cache = new CompiledCodeCache($cacheDir);

        // Compiler should be called when cache misses
        $compilerFacade = $this->createMock(CompilerFacadeInterface::class);
        $compilerFacade->expects($this->once())
            ->method('compileForCache')
            ->willReturn(new EmitterResult(false, '$compiled = true;', '', ''));

        $namespaceExtractor = $this->createStub(NamespaceExtractorInterface::class);
        $namespaceExtractor->method('getNamespaceFromFile')->willReturn(
            new NamespaceInformation($sourceFile, $namespace, ['phel.core']),
        );

        $evaluator = new FileEvaluator($compilerFacade, $namespaceExtractor, $cache);
        $result = $evaluator->evalFile($sourceFile);

        self::assertSame($sourceFile, $result->getSourceFile());
        self::assertSame($namespace, $result->getNamespace());
    }

    public function test_eval_file_stores_result_in_cache_after_compilation(): void
    {
        $sourceFile = $this->tempDir . '/test.phel';
        $sourceCode = '(ns test\\namespace)';
        file_put_contents($sourceFile, $sourceCode);
        $cacheDir = $this->tempDir . '/cache';
        $namespace = 'test\\namespace';
        $sourceHash = md5($sourceCode);
        $compiledCode = '$result = 123;';

        $cache = new CompiledCodeCache($cacheDir);

        $compilerFacade = $this->createStub(CompilerFacadeInterface::class);
        $compilerFacade->method('compileForCache')
            ->willReturn(new EmitterResult(false, $compiledCode, '', ''));

        $namespaceExtractor = $this->createStub(NamespaceExtractorInterface::class);
        $namespaceExtractor->method('getNamespaceFromFile')->willReturn(
            new NamespaceInformation($sourceFile, $namespace, ['phel.core']),
        );

        $evaluator = new FileEvaluator($compilerFacade, $namespaceExtractor, $cache);
        $evaluator->evalFile($sourceFile);

        // Verify cache now has the entry
        $cachedPath = $cache->get($sourceFile, $sourceHash);
        self::assertNotNull($cachedPath);
        self::assertFileExists($cachedPath);
        self::assertStringContainsString($compiledCode, (string) file_get_contents($cachedPath));
    }

    public function test_eval_file_caches_code_with_inline_source_map(): void
    {
        $sourceFile = $this->tempDir . '/test.phel';
        $sourceCode = '(ns test\\namespace)';
        file_put_contents($sourceFile, $sourceCode);
        $cacheDir = $this->tempDir . '/cache';
        $namespace = 'test\\namespace';

        $cache = new CompiledCodeCache($cacheDir);

        $compilerFacade = $this->createMock(CompilerFacadeInterface::class);
        $compilerFacade->expects(self::once())
            ->method('compileForCache')
            ->with($sourceCode, self::callback(
                static fn(CompileOptions $options): bool => $options->isSourceMapsEnabled(),
            ))
            ->willReturn(new EmitterResult(true, '$result = 123;', 'AAAA', $sourceFile));

        $namespaceExtractor = $this->createStub(NamespaceExtractorInterface::class);
        $namespaceExtractor->method('getNamespaceFromFile')->willReturn(
            new NamespaceInformation($sourceFile, $namespace, ['phel.core']),
        );

        $evaluator = new FileEvaluator($compilerFacade, $namespaceExtractor, $cache);
        $evaluator->evalFile($sourceFile);

        $cachedPath = $cache->get($sourceFile, md5($sourceCode));
        self::assertNotNull($cachedPath);
        $cachedCode = (string) file_get_contents($cachedPath);
        self::assertStringContainsString('// ' . $sourceFile, $cachedCode);
        self::assertStringContainsString('// ;;AAAA', $cachedCode);
    }

    public function test_eval_file_without_cache_compiles_directly(): void
    {
        $sourceFile = $this->tempDir . '/test.phel';
        file_put_contents($sourceFile, '(ns test\\namespace)');
        $namespace = 'test\\namespace';

        // No cache provided
        $compilerFacade = $this->createMock(CompilerFacadeInterface::class);
        $compilerFacade->expects($this->once())->method('eval');
        $compilerFacade->expects($this->never())->method('compileForCache');

        $namespaceExtractor = $this->createStub(NamespaceExtractorInterface::class);
        $namespaceExtractor->method('getNamespaceFromFile')->willReturn(
            new NamespaceInformation($sourceFile, $namespace, ['phel.core']),
        );

        $evaluator = new FileEvaluator($compilerFacade, $namespaceExtractor);
        $result = $evaluator->evalFile($sourceFile);

        self::assertSame($sourceFile, $result->getSourceFile());
        self::assertSame($namespace, $result->getNamespace());
        self::assertSame('', $result->getTargetFile());
    }

    public function test_eval_file_recompiles_when_source_hash_changes(): void
    {
        $sourceFile = $this->tempDir . '/test.phel';
        $cacheDir = $this->tempDir . '/cache';
        $namespace = 'test\\namespace';
        $oldCode = '(ns test\\namespace)';
        $newCode = '(ns test\\namespace) (def x 1)';
        $oldHash = md5($oldCode);

        // Put old version in cache
        file_put_contents($sourceFile, $oldCode);
        $cache = new CompiledCodeCache($cacheDir);
        $cache->put($sourceFile, $namespace, $oldHash, '$old = true;');

        // Update source file
        file_put_contents($sourceFile, $newCode);

        // Compiler should be called because hash changed
        $compilerFacade = $this->createMock(CompilerFacadeInterface::class);
        $compilerFacade->expects($this->once())
            ->method('compileForCache')
            ->willReturn(new EmitterResult(false, '$new = true;', '', ''));

        $namespaceExtractor = $this->createStub(NamespaceExtractorInterface::class);
        $namespaceExtractor->method('getNamespaceFromFile')->willReturn(
            new NamespaceInformation($sourceFile, $namespace, ['phel.core']),
        );

        $evaluator = new FileEvaluator($compilerFacade, $namespaceExtractor, $cache);
        $result = $evaluator->evalFile($sourceFile);

        self::assertSame($sourceFile, $result->getSourceFile());
    }

    public function test_eval_file_recovers_from_corrupt_cache(): void
    {
        $sourceFile = $this->tempDir . '/test.phel';
        $sourceCode = '(ns test\\namespace)';
        file_put_contents($sourceFile, $sourceCode);
        $cacheDir = $this->tempDir . '/cache';
        $namespace = 'test\\namespace';

        // Put corrupt PHP (syntax error) in cache
        $cache = new CompiledCodeCache($cacheDir);
        $cache->put($sourceFile, $namespace, md5($sourceCode), 'this is not valid php syntax {{{');

        $compilerFacade = $this->createStub(CompilerFacadeInterface::class);
        $compilerFacade->method('compileForCache')
            ->willReturn(new EmitterResult(false, '$result = 42;', '', ''));

        $namespaceExtractor = $this->createStub(NamespaceExtractorInterface::class);
        $namespaceExtractor->method('getNamespaceFromFile')->willReturn(
            new NamespaceInformation($sourceFile, $namespace, ['phel.core']),
        );

        $evaluator = new FileEvaluator($compilerFacade, $namespaceExtractor, $cache);
        $result = $evaluator->evalFile($sourceFile);

        self::assertSame($namespace, $result->getNamespace());
    }

    public function test_eval_file_propagates_user_code_exceptions(): void
    {
        $sourceFile = $this->tempDir . '/test.phel';
        $sourceCode = '(ns test\\namespace)';
        file_put_contents($sourceFile, $sourceCode);
        $cacheDir = $this->tempDir . '/cache';
        $namespace = 'test\\namespace';

        // Put code that throws a user exception in cache
        $cache = new CompiledCodeCache($cacheDir);
        $cache->put($sourceFile, $namespace, md5($sourceCode), 'throw new \\RuntimeException("User code error");');

        $compilerFacade = $this->createStub(CompilerFacadeInterface::class);
        $namespaceExtractor = $this->createStub(NamespaceExtractorInterface::class);
        $namespaceExtractor->method('getNamespaceFromFile')->willReturn(
            new NamespaceInformation($sourceFile, $namespace, ['phel.core']),
        );

        $evaluator = new FileEvaluator($compilerFacade, $namespaceExtractor, $cache);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('User code error');

        $evaluator->evalFile($sourceFile);
    }

    public function test_cache_hit_with_env_data_restores_environment(): void
    {
        $sourceFile = $this->tempDir . '/test.phel';
        $sourceCode = '(ns test\\namespace)';
        file_put_contents($sourceFile, $sourceCode);
        $cacheDir = $this->tempDir . '/cache';
        $namespace = 'test\\namespace';

        $cache = new CompiledCodeCache($cacheDir);
        $cache->put($sourceFile, $namespace, md5($sourceCode), '$result = 1;');

        $envData = [
            'refers' => ['map' => ['ns' => null, 'name' => 'phel.core']],
            'require_aliases' => [],
            'use_aliases' => [],
        ];
        $cache->putEnvironment($namespace, $envData);

        $compilerFacade = $this->createMock(CompilerFacadeInterface::class);
        $compilerFacade->expects($this->once())->method('initializeGlobalEnvironment');
        $compilerFacade->expects($this->once())
            ->method('restoreNamespaceEnvironmentData')
            ->with($namespace, $envData);
        // analyzeNsForm path should NOT be used when env data is present
        $compilerFacade->expects($this->never())->method('lexString');

        $namespaceExtractor = $this->createStub(NamespaceExtractorInterface::class);
        $namespaceExtractor->method('getNamespaceFromFile')->willReturn(
            new NamespaceInformation($sourceFile, $namespace, ['phel.core']),
        );

        $evaluator = new FileEvaluator($compilerFacade, $namespaceExtractor, $cache);
        $result = $evaluator->evalFile($sourceFile);

        self::assertSame($namespace, $result->getNamespace());
    }

    public function test_cache_hit_without_env_data_falls_back_to_analyze_ns_form(): void
    {
        $sourceFile = $this->tempDir . '/test.phel';
        $sourceCode = '(ns test\\namespace)';
        file_put_contents($sourceFile, $sourceCode);
        $cacheDir = $this->tempDir . '/cache';
        $namespace = 'test\\namespace';

        $cache = new CompiledCodeCache($cacheDir);
        $cache->put($sourceFile, $namespace, md5($sourceCode), '$result = 1;');
        // No putEnvironment call - simulating old cache without env data

        $compilerFacade = $this->createMock(CompilerFacadeInterface::class);
        $compilerFacade->expects($this->once())->method('initializeGlobalEnvironment');
        // restoreNamespaceEnvironmentData should NOT be called without env data
        $compilerFacade->expects($this->never())->method('restoreNamespaceEnvironmentData');
        // lexString should be called as fallback (analyzeNsForm path)
        $compilerFacade->expects($this->once())->method('lexString');

        $namespaceExtractor = $this->createStub(NamespaceExtractorInterface::class);
        $namespaceExtractor->method('getNamespaceFromFile')->willReturn(
            new NamespaceInformation($sourceFile, $namespace, ['phel.core']),
        );

        $evaluator = new FileEvaluator($compilerFacade, $namespaceExtractor, $cache);
        $result = $evaluator->evalFile($sourceFile);

        self::assertSame($namespace, $result->getNamespace());
    }

    public function test_cache_miss_stores_environment_data(): void
    {
        $sourceFile = $this->tempDir . '/test.phel';
        $sourceCode = '(ns test\\namespace)';
        file_put_contents($sourceFile, $sourceCode);
        $cacheDir = $this->tempDir . '/cache';
        $namespace = 'test\\namespace';

        $cache = new CompiledCodeCache($cacheDir);
        $envData = [
            'refers' => [],
            'require_aliases' => ['str' => ['ns' => null, 'name' => 'phel\\string']],
            'use_aliases' => [],
        ];

        $compilerFacade = $this->createMock(CompilerFacadeInterface::class);
        $compilerFacade->expects($this->once())
            ->method('compileForCache')
            ->willReturn(new EmitterResult(false, '$compiled = true;', '', ''));
        $compilerFacade->expects($this->once())
            ->method('getNamespaceEnvironmentData')
            ->with($namespace)
            ->willReturn($envData);

        $namespaceExtractor = $this->createStub(NamespaceExtractorInterface::class);
        $namespaceExtractor->method('getNamespaceFromFile')->willReturn(
            new NamespaceInformation($sourceFile, $namespace, ['phel.core']),
        );

        $evaluator = new FileEvaluator($compilerFacade, $namespaceExtractor, $cache);
        $evaluator->evalFile($sourceFile);

        // Verify env data was stored in cache
        $storedEnvData = $cache->getEnvironment($namespace);
        self::assertSame($envData, $storedEnvData);
    }

    public function test_cache_hit_calls_initialize_global_environment_and_lex_string(): void
    {
        $sourceFile = $this->tempDir . '/test.phel';
        $sourceCode = '(ns test\\namespace)';
        file_put_contents($sourceFile, $sourceCode);
        $cacheDir = $this->tempDir . '/cache';
        $namespace = 'test\\namespace';

        $cache = new CompiledCodeCache($cacheDir);
        $cache->put($sourceFile, $namespace, md5($sourceCode), '$result = 1;');

        $compilerFacade = $this->createMock(CompilerFacadeInterface::class);
        $compilerFacade->expects($this->once())->method('initializeGlobalEnvironment');
        $compilerFacade->expects($this->once())
            ->method('lexString')
            ->with($sourceCode, $sourceFile)
            ->willThrowException(new RuntimeException('lex failure'));

        $namespaceExtractor = $this->createStub(NamespaceExtractorInterface::class);
        $namespaceExtractor->method('getNamespaceFromFile')->willReturn(
            new NamespaceInformation($sourceFile, $namespace, ['phel.core']),
        );

        $evaluator = new FileEvaluator($compilerFacade, $namespaceExtractor, $cache);
        $result = $evaluator->evalFile($sourceFile);

        self::assertSame($namespace, $result->getNamespace());
    }

    public function test_cache_hit_survives_analysis_failure(): void
    {
        $sourceFile = $this->tempDir . '/test.phel';
        $sourceCode = '(ns test\\namespace)';
        file_put_contents($sourceFile, $sourceCode);
        $cacheDir = $this->tempDir . '/cache';
        $namespace = 'test\\namespace';

        $cache = new CompiledCodeCache($cacheDir);
        $cache->put($sourceFile, $namespace, md5($sourceCode), '$result = 1;');

        $compilerFacade = $this->createStub(CompilerFacadeInterface::class);
        $compilerFacade->method('lexString')
            ->willThrowException(new RuntimeException('analysis failure'));

        $namespaceExtractor = $this->createStub(NamespaceExtractorInterface::class);
        $namespaceExtractor->method('getNamespaceFromFile')->willReturn(
            new NamespaceInformation($sourceFile, $namespace, ['phel.core']),
        );

        $evaluator = new FileEvaluator($compilerFacade, $namespaceExtractor, $cache);
        $result = $evaluator->evalFile($sourceFile);

        // evalFile succeeds despite analysis failure
        self::assertSame($sourceFile, $result->getSourceFile());
        self::assertSame($namespace, $result->getNamespace());
    }

    public function test_register_dependencies_skipped_on_cache_hit(): void
    {
        $sourceFile = $this->tempDir . '/test.phel';
        $sourceCode = '(ns test\\namespace)';
        file_put_contents($sourceFile, $sourceCode);
        $cacheDir = $this->tempDir . '/cache';
        $namespace = 'test\\namespace';

        $cache = new CompiledCodeCache($cacheDir);
        $cache->put($sourceFile, $namespace, md5($sourceCode), '$result = 1;');

        $tracker = $this->createMock(DependencyTrackerInterface::class);
        $tracker->expects($this->never())->method('registerDependencies');

        $compilerFacade = $this->createStub(CompilerFacadeInterface::class);
        $namespaceExtractor = $this->createStub(NamespaceExtractorInterface::class);
        $namespaceExtractor->method('getNamespaceFromFile')->willReturn(
            new NamespaceInformation($sourceFile, $namespace, ['phel.core']),
        );

        $evaluator = new FileEvaluator(
            $compilerFacade,
            $namespaceExtractor,
            $cache,
            new FirstFormExtractor(),
            $tracker,
        );

        $evaluator->evalFile($sourceFile);
    }

    public function test_restore_environment_skipped_on_second_call_for_same_namespace(): void
    {
        $sourceFile = $this->tempDir . '/test.phel';
        $sourceCode = '(ns test\\namespace)';
        file_put_contents($sourceFile, $sourceCode);
        $cacheDir = $this->tempDir . '/cache';
        $namespace = 'test\\namespace';

        $cache = new CompiledCodeCache($cacheDir);
        $cache->put($sourceFile, $namespace, md5($sourceCode), '$result = 1;');
        $cache->putEnvironment($namespace, [
            'refers' => ['map' => ['ns' => null, 'name' => 'phel.core']],
            'require_aliases' => [],
            'use_aliases' => [],
        ]);

        $compilerFacade = $this->createMock(CompilerFacadeInterface::class);
        $compilerFacade->expects($this->exactly(2))->method('initializeGlobalEnvironment');
        $compilerFacade->expects($this->once())->method('restoreNamespaceEnvironmentData');

        $namespaceExtractor = $this->createStub(NamespaceExtractorInterface::class);
        $namespaceExtractor->method('getNamespaceFromFile')->willReturn(
            new NamespaceInformation($sourceFile, $namespace, ['phel.core']),
        );

        $evaluator = new FileEvaluator($compilerFacade, $namespaceExtractor, $cache);
        $evaluator->evalFile($sourceFile);
        $evaluator->evalFile($sourceFile);
    }

    public function test_register_dependencies_called_on_cache_miss(): void
    {
        $sourceFile = $this->tempDir . '/test.phel';
        $sourceCode = '(ns test\\namespace)';
        file_put_contents($sourceFile, $sourceCode);
        $cacheDir = $this->tempDir . '/cache';
        $namespace = 'test\\namespace';

        $cache = new CompiledCodeCache($cacheDir);

        $tracker = $this->createMock(DependencyTrackerInterface::class);
        $tracker->expects($this->once())
            ->method('registerDependencies')
            ->with($sourceFile, $namespace, ['phel.core']);

        $compilerFacade = $this->createStub(CompilerFacadeInterface::class);
        $compilerFacade->method('compileForCache')
            ->willReturn(new EmitterResult(false, '$compiled = true;', '', ''));

        $namespaceExtractor = $this->createStub(NamespaceExtractorInterface::class);
        $namespaceExtractor->method('getNamespaceFromFile')->willReturn(
            new NamespaceInformation($sourceFile, $namespace, ['phel.core']),
        );

        $evaluator = new FileEvaluator(
            $compilerFacade,
            $namespaceExtractor,
            $cache,
            new FirstFormExtractor(),
            $tracker,
        );

        $evaluator->evalFile($sourceFile);
    }

    public function test_eval_file_passes_optimization_level_to_compiler(): void
    {
        $sourceFile = $this->tempDir . '/test.phel';
        file_put_contents($sourceFile, '(ns test\\namespace)');
        $namespace = 'test\\namespace';

        $capturedOptions = null;
        $compilerFacade = $this->createStub(CompilerFacadeInterface::class);
        $compilerFacade->method('eval')
            ->willReturnCallback(static function (string $code, CompileOptions $options) use (&$capturedOptions): mixed {
                $capturedOptions = $options;

                return null;
            });

        $namespaceExtractor = $this->createStub(NamespaceExtractorInterface::class);
        $namespaceExtractor->method('getNamespaceFromFile')->willReturn(
            new NamespaceInformation($sourceFile, $namespace, ['phel.core']),
        );

        $evaluator = new FileEvaluator(
            $compilerFacade,
            $namespaceExtractor,
            optimizationLevel: 2,
        );
        $evaluator->evalFile($sourceFile);

        self::assertInstanceOf(CompileOptions::class, $capturedOptions);
        self::assertSame(2, $capturedOptions->getOptimizationLevel());
    }

    public function test_changing_optimization_level_invalidates_cached_code(): void
    {
        $sourceFile = $this->tempDir . '/test.phel';
        $sourceCode = '(ns test\\namespace)';
        file_put_contents($sourceFile, $sourceCode);
        $cacheDir = $this->tempDir . '/cache';
        $namespace = 'test\\namespace';

        // Entry stored under the plain level-0 hash must not satisfy a level-2 run.
        $cache = new CompiledCodeCache($cacheDir);
        $cache->put($sourceFile, $namespace, md5($sourceCode), '$level0 = true;');

        $capturedOptions = null;
        $compilerFacade = $this->createMock(CompilerFacadeInterface::class);
        $compilerFacade->expects($this->once())
            ->method('compileForCache')
            ->willReturnCallback(static function (string $code, CompileOptions $options) use (&$capturedOptions): EmitterResult {
                $capturedOptions = $options;

                return new EmitterResult(false, '$level2 = true;', '', '');
            });

        $namespaceExtractor = $this->createStub(NamespaceExtractorInterface::class);
        $namespaceExtractor->method('getNamespaceFromFile')->willReturn(
            new NamespaceInformation($sourceFile, $namespace, ['phel.core']),
        );

        $evaluator = new FileEvaluator(
            $compilerFacade,
            $namespaceExtractor,
            $cache,
            optimizationLevel: 2,
        );
        $evaluator->evalFile($sourceFile);

        self::assertInstanceOf(CompileOptions::class, $capturedOptions);
        self::assertSame(2, $capturedOptions->getOptimizationLevel());

        // A second level-2 run must hit the freshly stored level-2 entry.
        FileEvaluator::resetState();
        $secondFacade = $this->createMock(CompilerFacadeInterface::class);
        $secondFacade->expects($this->never())->method('compileForCache');
        $secondFacade->expects($this->never())->method('eval');

        $secondEvaluator = new FileEvaluator(
            $secondFacade,
            $namespaceExtractor,
            $cache,
            optimizationLevel: 2,
        );
        $result = $secondEvaluator->evalFile($sourceFile);

        self::assertSame($namespace, $result->getNamespace());
    }

    public function test_level_zero_keeps_plain_source_hash(): void
    {
        $sourceFile = $this->tempDir . '/test.phel';
        $sourceCode = '(ns test\\namespace)';
        file_put_contents($sourceFile, $sourceCode);
        $cacheDir = $this->tempDir . '/cache';
        $namespace = 'test\\namespace';

        // Pre-existing level-0 entry stays valid for a level-0 evaluator.
        $cache = new CompiledCodeCache($cacheDir);
        $cache->put($sourceFile, $namespace, md5($sourceCode), '$level0 = true;');

        $compilerFacade = $this->createMock(CompilerFacadeInterface::class);
        $compilerFacade->expects($this->never())->method('compileForCache');
        $compilerFacade->expects($this->never())->method('eval');

        $namespaceExtractor = $this->createStub(NamespaceExtractorInterface::class);
        $namespaceExtractor->method('getNamespaceFromFile')->willReturn(
            new NamespaceInformation($sourceFile, $namespace, ['phel.core']),
        );

        $evaluator = new FileEvaluator(
            $compilerFacade,
            $namespaceExtractor,
            $cache,
            optimizationLevel: 0,
        );
        $evaluator->evalFile($sourceFile);
    }

    /**
     * Writes a precompiled `.php` sibling that registers one def via
     * `\Phel::addDefinition`, mirroring the shape the PHAR bundles.
     */
    private function writePrecompiledDef(
        string $path,
        string $namespace,
        string $defName,
        string $valueExpr,
        string $trailer = '',
    ): void {
        file_put_contents(
            $path,
            "<?php declare(strict_types=1);\n"
            . sprintf(
                "%s::addDefinition(%s, %s, %s);\n",
                '\\' . Phel::class,
                var_export($namespace, true),
                var_export($defName, true),
                $valueExpr,
            )
            . $trailer,
        );
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }

        rmdir($dir);
    }
}
