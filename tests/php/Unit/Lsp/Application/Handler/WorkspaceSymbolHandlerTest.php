<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lsp\Application\Handler;

use Phel\Api\Transfer\Definition;
use Phel\Api\Transfer\ProjectIndex;
use Phel\Lsp\Application\Convert\PositionConverter;
use Phel\Lsp\Application\Convert\SymbolInformationBuilder;
use Phel\Lsp\Application\Convert\SymbolKindMapper;
use Phel\Lsp\Application\Convert\UriConverter;
use Phel\Lsp\Application\Document\DocumentStore;
use Phel\Lsp\Application\Handler\WorkspaceSymbolHandler;
use Phel\Lsp\Application\Session\Session;
use Phel\Lsp\Domain\NotificationSink;
use PHPUnit\Framework\TestCase;

final class WorkspaceSymbolHandlerTest extends TestCase
{
    public function test_method_and_response_type(): void
    {
        $handler = $this->handler();

        self::assertSame('workspace/symbol', $handler->method());
        self::assertFalse($handler->isNotification());
    }

    public function test_empty_list_when_project_not_indexed(): void
    {
        $session = $this->newSession();

        self::assertSame([], $this->handler()->handle(['query' => ''], $session));
    }

    public function test_returns_all_definitions_for_empty_query(): void
    {
        $session = $this->newSession();
        $session->setProjectIndex(new ProjectIndex([
            'core/foo' => $this->defn('core', 'foo'),
            'core/bar' => $this->defn('core', 'bar'),
        ], []));

        $result = $this->handler()->handle(['query' => ''], $session);

        self::assertCount(2, $result);
        self::assertSame('foo', $result[0]['name']);
        self::assertSame('core', $result[0]['containerName']);
    }

    public function test_filter_is_case_insensitive_substring(): void
    {
        $session = $this->newSession();
        $session->setProjectIndex(new ProjectIndex([
            'core/foo-bar' => $this->defn('core', 'foo-bar'),
            'core/baz' => $this->defn('core', 'baz'),
        ], []));

        $result = $this->handler()->handle(['query' => 'FOO'], $session);

        self::assertCount(1, $result);
        self::assertSame('foo-bar', $result[0]['name']);
    }

    public function test_non_string_query_is_treated_as_empty(): void
    {
        $session = $this->newSession();
        $session->setProjectIndex(new ProjectIndex([
            'core/foo' => $this->defn('core', 'foo'),
        ], []));

        $result = $this->handler()->handle(['query' => 123], $session);

        self::assertCount(1, $result);
    }

    private function defn(string $ns, string $name): Definition
    {
        return new Definition(
            $ns,
            $name,
            '/tmp/' . $name . '.phel',
            1,
            1,
            Definition::KIND_DEFN,
            [],
            '',
            false,
        );
    }

    private function handler(): WorkspaceSymbolHandler
    {
        $builder = new SymbolInformationBuilder(
            new PositionConverter(),
            new UriConverter(),
            new SymbolKindMapper(),
        );

        return new WorkspaceSymbolHandler($builder);
    }

    private function newSession(): Session
    {
        return new Session(new DocumentStore(), new class() implements NotificationSink {
            public function notify(string $method, array $params): void {}
        });
    }
}
