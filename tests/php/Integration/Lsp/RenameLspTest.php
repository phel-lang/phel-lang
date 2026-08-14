<?php

declare(strict_types=1);

namespace PhelTest\Integration\Lsp;

use Phel;
use Phel\Api\ApiFacade;
use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;
use Phel\Lsp\Application\Convert\PositionConverter;
use Phel\Lsp\Application\Convert\UriConverter;
use Phel\Lsp\Application\Document\DocumentStore;
use Phel\Lsp\Application\Handler\RenameHandler;
use Phel\Lsp\Application\Handler\SymbolResolver;
use Phel\Lsp\Application\Rpc\ParamsExtractor;
use Phel\Lsp\Application\Session\Session;
use Phel\Lsp\Domain\NotificationSink;
use PHPUnit\Framework\TestCase;

use function count;

/**
 * Applies the edits `textDocument/rename` returns to the source they came from.
 * Asserting the resulting text is the only way to catch a range that is merely
 * near the symbol: a shifted or duplicated edit silently rewrites source.
 */
final class RenameLspTest extends TestCase
{
    private const string FIXTURE_DIR = __DIR__ . '/Fixtures/rename';

    public function test_renaming_a_definition_rewrites_exactly_the_symbol(): void
    {
        // Line 2 is "(defn add [x] x)"; the caret sits on `add`.
        $renamed = $this->renameAt(line: 2, character: 6, newName: 'sub');

        self::assertSame("(ns demo)\n\n(defn sub [x] x)\n\n(sub 1)\n", $renamed);
    }

    public function test_renaming_from_a_usage_rewrites_exactly_the_symbol(): void
    {
        // Line 4 is "(add 1)"; the caret sits on `add`.
        $renamed = $this->renameAt(line: 4, character: 1, newName: 'sub');

        self::assertSame("(ns demo)\n\n(defn sub [x] x)\n\n(sub 1)\n", $renamed);
    }

    public function test_a_shorter_new_name_does_not_leave_a_remainder(): void
    {
        $renamed = $this->renameAt(line: 2, character: 6, newName: 'f');

        self::assertSame("(ns demo)\n\n(defn f [x] x)\n\n(f 1)\n", $renamed);
    }

    private function renameAt(int $line, int $character, string $newName): string
    {
        Phel::bootstrap(self::FIXTURE_DIR);
        Phel::clear();
        GlobalEnvironmentSingleton::initializeNew();

        $path = self::FIXTURE_DIR . '/demo.phel';
        $source = (string) file_get_contents($path);
        $uri = 'file://' . $path;

        $facade = new ApiFacade();
        $session = new Session(new DocumentStore(), new class() implements NotificationSink {
            public function notify(string $method, array $params): void {}
        });
        $session->documents()->open($uri, 'phel', 1, $source);
        $session->setProjectIndex($facade->indexProject([self::FIXTURE_DIR]));

        $handler = new RenameHandler(
            $facade,
            new PositionConverter(),
            new UriConverter(),
            new ParamsExtractor(),
            new SymbolResolver(),
        );

        $edit = $handler->handle([
            'textDocument' => ['uri' => $uri],
            'position' => ['line' => $line, 'character' => $character],
            'newName' => $newName,
        ], $session);

        self::assertIsArray($edit);
        self::assertArrayHasKey('changes', $edit);
        self::assertIsArray($edit['changes']);

        return $this->apply($source, $edit['changes'][$uri] ?? []);
    }

    /**
     * @param list<array{range: array{start: array{line: int, character: int}, end: array{line: int, character: int}}, newText: string}> $edits
     */
    private function apply(string $source, array $edits): string
    {
        self::assertNotSame([], $edits, 'rename produced no edit for the document');

        $lines = explode("\n", $source);

        // Later columns first, so an earlier edit's offsets stay valid.
        usort($edits, static fn(array $a, array $b): int => [$b['range']['start']['line'], $b['range']['start']['character']]
            <=> [$a['range']['start']['line'], $a['range']['start']['character']]);

        $applied = [];
        foreach ($edits as $edit) {
            $start = $edit['range']['start'];
            $end = $edit['range']['end'];
            self::assertSame($start['line'], $end['line'], 'rename edits never span lines');

            $key = $start['line'] . ':' . $start['character'];
            self::assertArrayNotHasKey($key, $applied, 'the same span was edited twice');
            $applied[$key] = true;

            $line = $lines[$start['line']];
            $lines[$start['line']] = mb_substr($line, 0, $start['character'])
                . $edit['newText']
                . mb_substr($line, $end['character']);
        }

        self::assertCount(count($edits), $applied);

        return implode("\n", $lines);
    }
}
