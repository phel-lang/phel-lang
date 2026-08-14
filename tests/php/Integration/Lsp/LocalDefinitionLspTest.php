<?php

declare(strict_types=1);

namespace PhelTest\Integration\Lsp;

use Phel;
use Phel\Api\ApiFacade;
use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;
use Phel\Lang\Symbol;
use Phel\Lsp\Application\Convert\LocationConverter;
use Phel\Lsp\Application\Convert\PositionConverter;
use Phel\Lsp\Application\Convert\UriConverter;
use Phel\Lsp\Application\Document\DocumentStore;
use Phel\Lsp\Application\Handler\DefinitionHandler;
use Phel\Lsp\Application\Handler\SymbolResolver;
use Phel\Lsp\Application\Rpc\ParamsExtractor;
use Phel\Lsp\Application\Session\Session;
use Phel\Lsp\Domain\NotificationSink;
use PHPUnit\Framework\TestCase;

/**
 * Drives `textDocument/definition` end to end over the real resolver, so a
 * disagreement between the resolver's 1-based Location and the 0-based LSP
 * range fails here instead of shipping.
 */
final class LocalDefinitionLspTest extends TestCase
{
    private const string URI = 'file:///project/demo.phel';

    public function test_it_navigates_from_a_let_usage_to_its_binding(): void
    {
        // Line 1 is "  mysym)"; the caret sits on the first character.
        $result = $this->definitionAt("(let [mysym 123]\n  mysym)", line: 1, character: 2);

        self::assertSame(self::URI, $result['uri'] ?? null);
        self::assertSame([
            'start' => ['line' => 0, 'character' => 6],
            'end' => ['line' => 0, 'character' => 11],
        ], $result['range'] ?? null);
    }

    public function test_it_navigates_from_a_caret_resting_past_the_usage(): void
    {
        $result = $this->definitionAt("(let [mysym 123]\n  mysym)", line: 1, character: 7);

        self::assertSame([
            'start' => ['line' => 0, 'character' => 6],
            'end' => ['line' => 0, 'character' => 11],
        ], $result['range'] ?? null);
    }

    public function test_it_returns_nothing_for_a_name_no_binding_introduces(): void
    {
        self::assertNull($this->definitionAt("(let [mysym 123]\n  other)", line: 1, character: 2));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function definitionAt(string $source, int $line, int $character): ?array
    {
        Phel::bootstrap(__DIR__);
        Phel::clear();
        Symbol::resetGen();
        GlobalEnvironmentSingleton::initializeNew();

        $session = new Session(new DocumentStore(), new class() implements NotificationSink {
            public function notify(string $method, array $params): void {}
        });
        $session->documents()->open(self::URI, 'phel', 1, $source);

        $handler = new DefinitionHandler(
            new ApiFacade(),
            new LocationConverter(new PositionConverter(), new UriConverter()),
            new ParamsExtractor(),
            new SymbolResolver(),
        );

        return $handler->handle([
            'textDocument' => ['uri' => self::URI],
            'position' => ['line' => $line, 'character' => $character],
        ], $session);
    }
}
