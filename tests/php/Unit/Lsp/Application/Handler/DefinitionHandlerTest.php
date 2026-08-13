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
use Phel\Shared\Api\Definition;
use Phel\Shared\Api\Location;
use Phel\Shared\Api\ProjectIndex;
use Phel\Shared\Facade\ApiFacadeInterface;
use PHPUnit\Framework\TestCase;

final class DefinitionHandlerTest extends TestCase
{
    public function test_it_navigates_from_required_namespace_to_ns_declaration(): void
    {
        $session = $this->sessionWith(
            "(ns demo\n  (:require phel.pprint :refer [pprint]))",
            new ProjectIndex([], [], ['phel.pprint' => new Location('/project/pprint.phel', 4, 8, 4, 19)]),
        );

        $result = $this->handle($session, line: 1, character: 15);

        self::assertSame('file:///project/pprint.phel', $result['uri'] ?? null);
        self::assertSame([
            'start' => ['line' => 3, 'character' => 7],
            'end' => ['line' => 3, 'character' => 18],
        ], $result['range'] ?? null);
    }

    public function test_it_navigates_from_a_backslash_spelled_require(): void
    {
        $session = $this->sessionWith(
            "(ns demo\n  (:require my-app\\core :refer [greet]))",
            new ProjectIndex([], [], ['my-app.core' => new Location('/project/core.phel', 1, 5, 1, 16)]),
        );

        $result = $this->handle($session, line: 1, character: 15);

        self::assertSame('file:///project/core.phel', $result['uri'] ?? null);
    }

    public function test_it_prefers_a_definition_whose_name_shadows_a_namespace(): void
    {
        $definition = new Definition('demo', 'my-app.core', '/project/demo.phel', 9, 3, Definition::KIND_DEF, [], '', false);
        $session = $this->sessionWith(
            "(ns demo\n  (:require my-app.core :refer [greet]))",
            new ProjectIndex(
                ['demo/my-app.core' => $definition],
                [],
                ['my-app.core' => new Location('/project/core.phel', 1, 5, 1, 16)],
            ),
        );

        $result = $this->handle($session, line: 1, character: 15);

        self::assertSame('file:///project/demo.phel', $result['uri'] ?? null);
    }

    public function test_it_returns_null_for_an_unknown_namespace(): void
    {
        $session = $this->sessionWith(
            "(ns demo\n  (:require my-app.missing))",
            new ProjectIndex([], [], ['my-app.core' => new Location('/project/core.phel', 1, 5, 1, 16)]),
        );

        self::assertNull($this->handle($session, line: 1, character: 15));
    }

    public function test_it_prefers_a_local_let_binding_over_a_project_definition(): void
    {
        $index = new ProjectIndex(
            ['demo/mysym' => new Definition('demo', 'mysym', '/project/global.phel', 9, 3, Definition::KIND_DEF, [], '', false)],
            [],
            [],
        );
        $session = $this->sessionWith(
            "(let [mysym 123]\n    mysym)",
            $index,
        );

        $facade = $this->createStub(ApiFacadeInterface::class);
        $facade->method('resolveLocalBinding')
            ->willReturn(new Location('file:///project/demo.phel', 1, 7, 1, 12));

        $result = $this->handle($session, line: 1, character: 4, facade: $facade);

        self::assertSame('file:///project/demo.phel', $result['uri'] ?? null);
        self::assertSame([
            'start' => ['line' => 0, 'character' => 6],
            'end' => ['line' => 0, 'character' => 11],
        ], $result['range'] ?? null);
    }

    public function test_it_resolves_a_local_binding_before_the_project_index_exists(): void
    {
        $session = $this->sessionWith("(let [mysym 123]\n    mysym)");

        $facade = $this->createStub(ApiFacadeInterface::class);
        $facade->method('resolveLocalBinding')
            ->willReturn(new Location('file:///project/demo.phel', 1, 7, 1, 12));

        $result = $this->handle($session, line: 1, character: 4, facade: $facade);

        self::assertSame('file:///project/demo.phel', $result['uri'] ?? null);
        self::assertSame([
            'start' => ['line' => 0, 'character' => 6],
            'end' => ['line' => 0, 'character' => 11],
        ], $result['range'] ?? null);
    }

    public function test_it_falls_back_to_the_project_index_when_there_is_no_local_binding(): void
    {
        $index = new ProjectIndex(
            ['demo/mysym' => new Definition('demo', 'mysym', '/project/global.phel', 9, 3, Definition::KIND_DEF, [], '', false)],
            [],
            [],
        );
        $session = $this->sessionWith(
            "(let [other 123]\n    mysym)",
            $index,
        );

        $facade = $this->createStub(ApiFacadeInterface::class);
        $facade->method('resolveLocalBinding')->willReturn(null);

        $result = $this->handle($session, line: 1, character: 4, facade: $facade);

        self::assertSame('file:///project/global.phel', $result['uri'] ?? null);
    }

    private function sessionWith(string $source, ?ProjectIndex $index = null): Session
    {
        $session = new Session(new DocumentStore(), new class() implements NotificationSink {
            public function notify(string $method, array $params): void {}
        });
        $session->documents()->open('file:///project/demo.phel', 'phel', 1, $source);
        if ($index instanceof ProjectIndex) {
            $session->setProjectIndex($index);
        }

        return $session;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function handle(Session $session, int $line, int $character, ?ApiFacadeInterface $facade = null): ?array
    {
        $handler = new DefinitionHandler(
            $facade ?? $this->createStub(ApiFacadeInterface::class),
            new LocationConverter(new PositionConverter(), new UriConverter()),
            new ParamsExtractor(),
            new SymbolResolver(),
        );

        return $handler->handle([
            'textDocument' => ['uri' => 'file:///project/demo.phel'],
            'position' => ['line' => $line, 'character' => $character],
        ], $session);
    }
}
