<?php

declare(strict_types=1);

namespace PhelTest\Integration\Lsp;

use Phel;
use Phel\Api\ApiFacade;
use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;
use Phel\Lang\Symbol;
use Phel\Lsp\Application\Convert\CompletionConverter;
use Phel\Lsp\Application\Document\DocumentStore;
use Phel\Lsp\Application\Handler\CompletionHandler;
use Phel\Lsp\Application\Handler\HoverHandler;
use Phel\Lsp\Application\Handler\SignatureHelpHandler;
use Phel\Lsp\Application\Handler\SymbolResolver;
use Phel\Lsp\Application\Rpc\ParamsExtractor;
use Phel\Lsp\Application\Session\Session;
use Phel\Lsp\Domain\NotificationSink;
use PhelTest\Unit\Api\Application\Fixtures\ChainFixture;
use PhelTest\Unit\Api\Application\Fixtures\HoverFixture;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

use function array_column;
use function strlen;

final class PhpInteropLspTest extends TestCase
{
    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_completion_lists_instance_methods_for_tagged_receiver(): void
    {
        $uri = 'file:///x.phel';
        $source = "(let [^\\DateTimeImmutable dt (x)]\n  (php/-> dt (get))";
        // Cursor right after "(get".
        $session = $this->sessionWith($uri, $source);

        $result = $this->completion()->handle(
            $this->params($uri, line: 1, character: 13),
            $session,
        );

        $labels = array_column($result['items'], 'label');
        self::assertContains('getTimestamp', $labels);
        self::assertContains('getOffset', $labels);
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_completion_lists_static_members_for_use_aliased_class(): void
    {
        $uri = 'file:///x.phel';
        $source = "(ns app (:use DateTimeImmutable :as DT))\n(php/:: DT createFr)";
        $session = $this->sessionWith($uri, $source);

        // Cursor right after "createFr" on line 2.
        $result = $this->completion()->handle(
            $this->params($uri, line: 1, character: 19),
            $session,
        );

        $labels = array_column($result['items'], 'label');
        self::assertContains('createFromFormat', $labels);
        self::assertContains('createFromInterface', $labels);
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_completion_lists_instance_methods_for_multiline_receiver(): void
    {
        $uri = 'file:///x.phel';
        $source = "(php/-> (php/new \\DateTimeImmutable)\n  (getTim))";
        $session = $this->sessionWith($uri, $source);

        // Cursor right after "(getTim" on line 2.
        $result = $this->completion()->handle(
            $this->params($uri, line: 1, character: 9),
            $session,
        );

        $labels = array_column($result['items'], 'label');
        self::assertContains('getTimestamp', $labels);
        self::assertContains('getTimezone', $labels);
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_completion_lists_methods_through_a_chained_receiver(): void
    {
        $uri = 'file:///x.phel';
        $source = '(php/-> (php/new \\' . ChainFixture::class . ') (withName "a") (nex';
        $session = $this->sessionWith($uri, $source);

        // Cursor right after "(nex": the receiver type comes from withName()'s
        // return type, not the constructor.
        $result = $this->completion()->handle(
            $this->params($uri, line: 0, character: strlen($source)),
            $session,
        );

        $labels = array_column($result['items'], 'label');
        self::assertContains('next', $labels, 'receiver type advanced through the withName() hop');
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_completion_lists_class_names_after_php_new(): void
    {
        $uri = 'file:///x.phel';
        $source = '(php/new \\DateTimeImm)';
        $session = $this->sessionWith($uri, $source);

        // Cursor right after "DateTimeImm".
        $result = $this->completion()->handle(
            $this->params($uri, line: 0, character: 20),
            $session,
        );

        $labels = array_column($result['items'], 'label');
        self::assertContains('DateTimeImmutable', $labels);
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_hover_shows_global_function_signature(): void
    {
        $uri = 'file:///x.phel';
        $source = '(php/strlen x)';
        $session = $this->sessionWith($uri, $source);

        // Cursor on "strlen".
        $result = $this->hover()->handle(
            $this->params($uri, line: 0, character: 8),
            $session,
        );

        self::assertIsArray($result);
        self::assertStringContainsString('strlen(', (string) $result['contents']['value']);
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_hover_shows_instance_property_with_phpdoc(): void
    {
        $uri = 'file:///x.phel';
        $source = '(let [^\\' . HoverFixture::class . " obj (x)]\n  (php/-> obj count))";
        $session = $this->sessionWith($uri, $source);

        // Cursor on "count" on line 2.
        $result = $this->hover()->handle(
            $this->params($uri, line: 1, character: 15),
            $session,
        );

        self::assertIsArray($result);
        self::assertStringContainsString('int $count', (string) $result['contents']['value']);
        self::assertStringContainsString('The current count', (string) $result['contents']['value']);
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_signature_help_for_php_new(): void
    {
        $uri = 'file:///x.phel';
        $source = '(php/new \\DateTimeImmutable )';
        $session = $this->sessionWith($uri, $source);

        // Cursor inside the argument list.
        $result = $this->signatureHelp()->handle(
            $this->params($uri, line: 0, character: 28),
            $session,
        );

        self::assertIsArray($result);
        self::assertStringContainsString('new', (string) $result['signatures'][0]['label']);
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_signature_help_targets_innermost_chained_method(): void
    {
        $uri = 'file:///x.phel';
        $source = '(php/-> (php/new \\DateTimeImmutable) (modify "x") (setDate 2020 ';
        $session = $this->sessionWith($uri, $source);

        // Cursor past the first argument of the innermost `setDate` call.
        $result = $this->signatureHelp()->handle(
            $this->params($uri, line: 0, character: strlen($source)),
            $session,
        );

        self::assertIsArray($result);
        $signature = $result['signatures'][0];
        self::assertStringContainsString('setDate(', (string) $signature['label']);
        self::assertCount(3, $signature['parameters']);
        self::assertSame(1, $result['activeParameter']);
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_completion_falls_back_to_phel_for_plain_code(): void
    {
        $uri = 'file:///x.phel';
        $source = '(de)';
        $session = $this->sessionWith($uri, $source);

        // Cursor after "(de".
        $result = $this->completion()->handle(
            $this->params($uri, line: 0, character: 3),
            $session,
        );

        $labels = array_column($result['items'], 'label');
        self::assertContains('def', $labels, 'plain Phel completion still works');
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_completion_inside_string_does_not_offer_php_classes(): void
    {
        $uri = 'file:///x.phel';
        $source = '(println "see \\DateTimeImm';
        $session = $this->sessionWith($uri, $source);

        // Cursor inside the (unterminated) string literal.
        $result = $this->completion()->handle(
            $this->params($uri, line: 0, character: strlen($source)),
            $session,
        );

        $labels = array_column($result['items'], 'label');
        self::assertNotContains('DateTimeImmutable', $labels, 'interop suppressed inside a string');
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_completion_falls_back_to_phel_when_interop_has_no_match(): void
    {
        $uri = 'file:///x.phel';
        // `ma` matches no DateTimeImmutable instance method, so completion must
        // fall through to Phel rather than returning an empty list.
        $source = '(php/-> (php/new \\DateTimeImmutable) ma';
        $session = $this->sessionWith($uri, $source);

        $result = $this->completion()->handle(
            $this->params($uri, line: 0, character: strlen($source)),
            $session,
        );

        $labels = array_column($result['items'], 'label');
        self::assertContains('map', $labels, 'Phel core completion offered when interop has no match');
    }

    private function completion(): CompletionHandler
    {
        return new CompletionHandler(
            $this->apiFacade(),
            new CompletionConverter(),
            new ParamsExtractor(),
        );
    }

    private function hover(): HoverHandler
    {
        return new HoverHandler($this->apiFacade(), new ParamsExtractor(), new SymbolResolver());
    }

    private function signatureHelp(): SignatureHelpHandler
    {
        return new SignatureHelpHandler($this->apiFacade(), new ParamsExtractor());
    }

    private function apiFacade(): ApiFacade
    {
        Phel::bootstrap(__DIR__);
        Phel::clear();
        Symbol::resetGen();
        GlobalEnvironmentSingleton::initializeNew();

        return new ApiFacade();
    }

    private function sessionWith(string $uri, string $source): Session
    {
        $session = new Session(new DocumentStore(), new class() implements NotificationSink {
            public function notify(string $method, array $params): void {}
        });
        $session->documents()->open($uri, 'phel', 1, $source);

        return $session;
    }

    /**
     * @return array<string, mixed>
     */
    private function params(string $uri, int $line, int $character): array
    {
        return [
            'textDocument' => ['uri' => $uri],
            'position' => ['line' => $line, 'character' => $character],
        ];
    }
}
