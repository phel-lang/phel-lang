<?php

declare(strict_types=1);

namespace Phel\Api;

use Gacela\Framework\AbstractFactory;
use Phel\Api\Application\Analysis\LexAndParseStage;
use Phel\Api\Application\Analysis\ReadAndAnalyzeStage;
use Phel\Api\Application\PhelFnGroupKeyGenerator;
use Phel\Api\Application\PhelFnNormalizer;
use Phel\Api\Application\PointCompleter;
use Phel\Api\Application\ProjectIndexer;
use Phel\Api\Application\ReferenceFinder;
use Phel\Api\Application\ReplCompleter;
use Phel\Api\Application\SourceAnalyzer;
use Phel\Api\Application\SymbolExtractor;
use Phel\Api\Application\SymbolResolver;
use Phel\Api\Domain\PhelFnGroupKeyGeneratorInterface;
use Phel\Api\Domain\PhelFnLoaderInterface;
use Phel\Api\Domain\PhelFnNormalizerInterface;
use Phel\Api\Domain\PointCompleterInterface;
use Phel\Api\Domain\ProjectIndexerInterface;
use Phel\Api\Domain\ReferenceFinderInterface;
use Phel\Api\Domain\ReplCompleterInterface;
use Phel\Api\Domain\SourceAnalyzerInterface;
use Phel\Api\Domain\SymbolResolverInterface;
use Phel\Api\Infrastructure\Daemon\ApiDaemon;
use Phel\Api\Infrastructure\PhelFnLoader;
use Phel\Api\Infrastructure\PhelFunctionRuntimeLoader;
use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;
use Phel\Shared\Facade\CompilerFacadeInterface;
use Phel\Shared\Facade\RunFacadeInterface;

/**
 * @extends AbstractFactory<ApiConfig>
 */
final class ApiFactory extends AbstractFactory
{
    public function createReplCompleter(): ReplCompleterInterface
    {
        return new ReplCompleter(
            $this->createPhelFnLoader(),
            $this->getConfig()->allNamespaces(),
            GlobalEnvironmentSingleton::getInstance(),
        );
    }

    public function createPhelFnNormalizer(): PhelFnNormalizerInterface
    {
        return new PhelFnNormalizer(
            $this->createPhelFnLoader(),
            $this->createPhelFnGroupKeyGenerator(),
            $this->getConfig()->allNamespaces(),
        );
    }

    public function createSourceAnalyzer(): SourceAnalyzerInterface
    {
        $compilerFacade = $this->getCompilerFacade();

        return new SourceAnalyzer([
            new LexAndParseStage($compilerFacade),
            new ReadAndAnalyzeStage($compilerFacade),
        ]);
    }

    public function createProjectIndexer(): ProjectIndexerInterface
    {
        return new ProjectIndexer(
            $this->createSymbolExtractor(),
        );
    }

    public function createSymbolResolver(): SymbolResolverInterface
    {
        return new SymbolResolver();
    }

    public function createReferenceFinder(): ReferenceFinderInterface
    {
        return new ReferenceFinder();
    }

    public function createPointCompleter(): PointCompleterInterface
    {
        return new PointCompleter(
            $this->getCompilerFacade(),
            $this->createPhelFnNormalizer(),
        );
    }

    public function createApiDaemon(ApiFacade $facade): ApiDaemon
    {
        return new ApiDaemon($facade);
    }

    public function createSymbolExtractor(): SymbolExtractor
    {
        return new SymbolExtractor(
            $this->getCompilerFacade(),
        );
    }

    public function getRunFacade(): RunFacadeInterface
    {
        /** @var RunFacadeInterface $facade */
        $facade = $this->getProvidedDependency(ApiProvider::FACADE_RUN);

        return $facade;
    }

    private function createPhelFnLoader(): PhelFnLoaderInterface
    {
        return new PhelFnLoader(
            new PhelFunctionRuntimeLoader($this->getRunFacade()),
        );
    }

    private function createPhelFnGroupKeyGenerator(): PhelFnGroupKeyGeneratorInterface
    {
        return new PhelFnGroupKeyGenerator();
    }

    private function getCompilerFacade(): CompilerFacadeInterface
    {
        /** @var CompilerFacadeInterface $facade */
        $facade = $this->getProvidedDependency(ApiProvider::FACADE_COMPILER);

        return $facade;
    }
}
