<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lsp\Application\Handler;

use Phel\Lsp\Application\Convert\LocationConverter;
use Phel\Lsp\Application\Convert\PositionConverter;
use Phel\Lsp\Application\Convert\UriConverter;
use Phel\Lsp\Application\Document\DocumentStore;
use Phel\Lsp\Application\Handler\DefinitionHandler;
use Phel\Lsp\Application\Handler\SymbolResolver;
use Phel\Lsp\Application\Rpc\ParamsExtractor;
use Phel\Lsp\Application\Session\Session;
use Phel\Lsp\Domain\NotificationSink;
use Phel\Shared\Api\Location;
use Phel\Shared\Api\ProjectIndex;
use Phel\Shared\Facade\ApiFacadeInterface;
use PHPUnit\Framework\TestCase;

final class DefinitionHandlerTest extends TestCase
{
    public function test_it_navigates_from_required_namespace_to_ns_declaration(): void
    {
        $session = new Session(new DocumentStore(), new class() implements NotificationSink {
            public function notify(string $method, array $params): void {}
        });
        $session->documents()->open(
            'file:///project/demo.phel',
            'phel',
            1,
            "(ns demo\n  (:require phel.pprint :refer [pprint]))",
        );
        $session->setProjectIndex(new ProjectIndex(
            [],
            ['phel.pprint/' => [new Location('/project/pprint.phel', 4, 8, 4, 19)]],
        ));

        $handler = new DefinitionHandler(
            $this->createStub(ApiFacadeInterface::class),
            new LocationConverter(new PositionConverter(), new UriConverter()),
            new ParamsExtractor(),
            new SymbolResolver(),
        );

        $result = $handler->handle([
            'textDocument' => ['uri' => 'file:///project/demo.phel'],
            'position' => ['line' => 1, 'character' => 15],
        ], $session);

        self::assertSame('file:///project/pprint.phel', $result['uri'] ?? null);
        self::assertSame([
            'start' => ['line' => 3, 'character' => 7],
            'end' => ['line' => 3, 'character' => 18],
        ], $result['range'] ?? null);
    }
}
