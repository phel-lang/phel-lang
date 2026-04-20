<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lsp\Application\Handler;

use Phel\Api\Transfer\ProjectIndex;
use Phel\Lsp\Application\Document\DocumentStore;
use Phel\Lsp\Application\Handler\CursorContext;
use Phel\Lsp\Application\Rpc\ParamsExtractor;
use Phel\Lsp\Application\Session\Session;
use Phel\Lsp\Domain\NotificationSink;
use PHPUnit\Framework\TestCase;

final class CursorContextTest extends TestCase
{
    public function test_null_when_project_index_required_but_absent(): void
    {
        $session = $this->newSession();

        $result = CursorContext::resolve($this->validParams(), $session, new ParamsExtractor());

        self::assertNull($result);
    }

    public function test_null_when_uri_missing_even_with_index(): void
    {
        $session = $this->newSession();
        $session->setProjectIndex(new ProjectIndex([], []));

        $result = CursorContext::resolve(
            ['position' => ['line' => 0, 'character' => 0]],
            $session,
            new ParamsExtractor(),
        );

        self::assertNull($result);
    }

    public function test_null_when_position_missing(): void
    {
        $session = $this->newSession();
        $session->setProjectIndex(new ProjectIndex([], []));

        $result = CursorContext::resolve(
            ['textDocument' => ['uri' => 'file:///x.phel']],
            $session,
            new ParamsExtractor(),
        );

        self::assertNull($result);
    }

    public function test_null_when_document_not_open(): void
    {
        $session = $this->newSession();
        $session->setProjectIndex(new ProjectIndex([], []));

        $result = CursorContext::resolve(
            $this->validParams('file:///nonexistent.phel'),
            $session,
            new ParamsExtractor(),
        );

        self::assertNull($result);
    }

    public function test_null_when_cursor_is_on_whitespace(): void
    {
        $session = $this->newSession();
        $session->setProjectIndex(new ProjectIndex([], []));
        $session->documents()->open('file:///x.phel', 'phel', 1, '   ');

        $result = CursorContext::resolve(
            $this->validParams('file:///x.phel'),
            $session,
            new ParamsExtractor(),
        );

        self::assertNull($result);
    }

    public function test_returns_populated_context(): void
    {
        $session = $this->newSession();
        $index = new ProjectIndex([], []);
        $session->setProjectIndex($index);
        $session->documents()->open('file:///x.phel', 'phel', 1, '(foo-bar 1)');

        $result = CursorContext::resolve(
            [
                'textDocument' => ['uri' => 'file:///x.phel'],
                'position' => ['line' => 0, 'character' => 2],
            ],
            $session,
            new ParamsExtractor(),
        );

        self::assertNotNull($result);
        self::assertSame('foo-bar', $result->word);
        self::assertSame(['line' => 0, 'character' => 2], $result->position);
        self::assertSame($index, $result->index);
    }

    public function test_require_index_false_allows_missing_index(): void
    {
        $session = $this->newSession();
        $session->documents()->open('file:///x.phel', 'phel', 1, '(foo 1)');

        $result = CursorContext::resolve(
            [
                'textDocument' => ['uri' => 'file:///x.phel'],
                'position' => ['line' => 0, 'character' => 2],
            ],
            $session,
            new ParamsExtractor(),
            requireIndex: false,
        );

        self::assertNotNull($result);
        self::assertSame('foo', $result->word);
    }

    /**
     * @return array<string, mixed>
     */
    private function validParams(string $uri = 'file:///x.phel'): array
    {
        return [
            'textDocument' => ['uri' => $uri],
            'position' => ['line' => 0, 'character' => 0],
        ];
    }

    private function newSession(): Session
    {
        return new Session(new DocumentStore(), new class() implements NotificationSink {
            public function notify(string $method, array $params): void {}
        });
    }
}
