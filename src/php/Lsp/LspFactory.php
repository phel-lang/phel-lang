<?php

declare(strict_types=1);

namespace Phel\Lsp;

use Gacela\Framework\AbstractFactory;
use Phel\Api\ApiFacade;
use Phel\Command\CommandFacade;
use Phel\Compiler\CompilerFacade;
use Phel\Formatter\FormatterFacade;
use Phel\Lint\LintFacade;
use Phel\Lsp\Application\Convert\CompletionConverter;
use Phel\Lsp\Application\Convert\DiagnosticConverter;
use Phel\Lsp\Application\Convert\LocationConverter;
use Phel\Lsp\Application\Convert\PositionConverter;
use Phel\Lsp\Application\Convert\SymbolInformationBuilder;
use Phel\Lsp\Application\Convert\SymbolKindMapper;
use Phel\Lsp\Application\Convert\UriConverter;
use Phel\Lsp\Application\Diagnostics\DiagnosticPublisher;
use Phel\Lsp\Application\Document\ContentChangeApplier;
use Phel\Lsp\Application\Document\DocumentStore;
use Phel\Lsp\Application\Handler\CompletionHandler;
use Phel\Lsp\Application\Handler\DefinitionHandler;
use Phel\Lsp\Application\Handler\DidChangeHandler;
use Phel\Lsp\Application\Handler\DidCloseHandler;
use Phel\Lsp\Application\Handler\DidOpenHandler;
use Phel\Lsp\Application\Handler\DidSaveHandler;
use Phel\Lsp\Application\Handler\DocumentSymbolHandler;
use Phel\Lsp\Application\Handler\ExitHandler;
use Phel\Lsp\Application\Handler\FormattingHandler;
use Phel\Lsp\Application\Handler\HoverHandler;
use Phel\Lsp\Application\Handler\InitializedHandler;
use Phel\Lsp\Application\Handler\InitializeHandler;
use Phel\Lsp\Application\Handler\ReferencesHandler;
use Phel\Lsp\Application\Handler\RenameHandler;
use Phel\Lsp\Application\Handler\ShutdownHandler;
use Phel\Lsp\Application\Handler\SymbolResolver;
use Phel\Lsp\Application\Handler\WorkspaceSymbolHandler;
use Phel\Lsp\Application\Rpc\LspServer;
use Phel\Lsp\Application\Rpc\MessageReader;
use Phel\Lsp\Application\Rpc\MessageWriter;
use Phel\Lsp\Application\Rpc\ParamsExtractor;
use Phel\Lsp\Application\Rpc\RequestDispatcher;
use Phel\Lsp\Application\Rpc\ResponseBuilder;
use Phel\Lsp\Application\Rpc\StreamNotificationSink;
use Phel\Lsp\Application\Session\Session;
use Phel\Run\RunFacade;

/**
 * @extends AbstractFactory<LspConfig>
 */
final class LspFactory extends AbstractFactory
{
    /**
     * @param resource $input
     * @param resource $output
     */
    public function createServer($input, $output): LspServer
    {
        $responses = $this->createResponseBuilder();
        $writer = $this->createMessageWriter();
        $sink = new StreamNotificationSink($writer, $responses, $output);
        $session = new Session($this->createDocumentStore(), $sink);
        $dispatcher = $this->createDispatcher($responses);

        return new LspServer(
            $this->createMessageReader(),
            $writer,
            $dispatcher,
            $responses,
            $session,
        );
    }

    public function createDispatcher(?ResponseBuilder $responses = null): RequestDispatcher
    {
        $dispatcher = new RequestDispatcher($responses ?? $this->createResponseBuilder());

        $publisher = $this->createDiagnosticPublisher();
        $positions = $this->createPositionConverter();
        $uris = $this->createUriConverter();
        $locations = $this->createLocationConverter();
        $completions = $this->createCompletionConverter();
        $params = $this->createParamsExtractor();
        $symbols = $this->createSymbolResolver();
        $symbolBuilder = $this->createSymbolInformationBuilder();

        $dispatcher->register(new InitializeHandler($this->getApiFacade(), $uris));
        $dispatcher->register(new InitializedHandler());
        $dispatcher->register(new ShutdownHandler());
        $dispatcher->register(new ExitHandler());

        $dispatcher->register(new DidOpenHandler($publisher, $params));
        $dispatcher->register(new DidChangeHandler($publisher, $params, $this->createContentChangeApplier()));
        $dispatcher->register(new DidCloseHandler($params));
        $dispatcher->register(new DidSaveHandler($publisher, $this->getApiFacade(), $uris, $params));

        $dispatcher->register(new HoverHandler($this->getApiFacade(), $params, $symbols));
        $dispatcher->register(new DefinitionHandler($this->getApiFacade(), $locations, $params, $symbols));
        $dispatcher->register(new ReferencesHandler($this->getApiFacade(), $locations, $params, $symbols));
        $dispatcher->register(new CompletionHandler($this->getApiFacade(), $completions, $params));
        $dispatcher->register(new DocumentSymbolHandler($this->getApiFacade(), $uris, $symbolBuilder, $params));
        $dispatcher->register(new WorkspaceSymbolHandler($symbolBuilder));
        $dispatcher->register(new RenameHandler($this->getApiFacade(), $positions, $uris, $params, $symbols));
        $dispatcher->register(new FormattingHandler($this->getFormatterFacade(), $params));

        return $dispatcher;
    }

    public function createMessageReader(): MessageReader
    {
        return new MessageReader();
    }

    public function createMessageWriter(): MessageWriter
    {
        return new MessageWriter();
    }

    public function createResponseBuilder(): ResponseBuilder
    {
        return new ResponseBuilder();
    }

    public function createDocumentStore(): DocumentStore
    {
        return new DocumentStore();
    }

    public function createPositionConverter(): PositionConverter
    {
        return new PositionConverter();
    }

    public function createUriConverter(): UriConverter
    {
        return new UriConverter();
    }

    public function createLocationConverter(): LocationConverter
    {
        return new LocationConverter(
            $this->createPositionConverter(),
            $this->createUriConverter(),
        );
    }

    public function createDiagnosticConverter(): DiagnosticConverter
    {
        return new DiagnosticConverter(
            $this->createPositionConverter(),
            $this->createUriConverter(),
        );
    }

    public function createCompletionConverter(): CompletionConverter
    {
        return new CompletionConverter();
    }

    public function createSymbolKindMapper(): SymbolKindMapper
    {
        return new SymbolKindMapper();
    }

    public function createSymbolInformationBuilder(): SymbolInformationBuilder
    {
        return new SymbolInformationBuilder(
            $this->createPositionConverter(),
            $this->createUriConverter(),
            $this->createSymbolKindMapper(),
        );
    }

    public function createParamsExtractor(): ParamsExtractor
    {
        return new ParamsExtractor();
    }

    public function createContentChangeApplier(): ContentChangeApplier
    {
        return new ContentChangeApplier($this->createParamsExtractor());
    }

    public function createSymbolResolver(): SymbolResolver
    {
        return new SymbolResolver();
    }

    public function createDiagnosticPublisher(): DiagnosticPublisher
    {
        return new DiagnosticPublisher(
            $this->getApiFacade(),
            $this->getLintFacade(),
            $this->createDiagnosticConverter(),
            LspConfig::defaultDiagnosticDebounceMs(),
        );
    }

    public function getApiFacade(): ApiFacade
    {
        return $this->getProvidedDependency(LspProvider::FACADE_API);
    }

    public function getLintFacade(): LintFacade
    {
        return $this->getProvidedDependency(LspProvider::FACADE_LINT);
    }

    public function getFormatterFacade(): FormatterFacade
    {
        return $this->getProvidedDependency(LspProvider::FACADE_FORMATTER);
    }

    public function getCompilerFacade(): CompilerFacade
    {
        return $this->getProvidedDependency(LspProvider::FACADE_COMPILER);
    }

    public function getCommandFacade(): CommandFacade
    {
        return $this->getProvidedDependency(LspProvider::FACADE_COMMAND);
    }

    public function getRunFacade(): RunFacade
    {
        return $this->getProvidedDependency(LspProvider::FACADE_RUN);
    }
}
