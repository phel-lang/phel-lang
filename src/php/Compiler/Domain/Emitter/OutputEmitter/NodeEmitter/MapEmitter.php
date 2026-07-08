<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Ast\MapNode;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitterInterface;
use Phel\Compiler\Domain\Emitter\OutputEmitter\PhpStringEscape;
use Phel\Lang\Keyword;
use Phel\Lang\SourceLocation;

use function array_key_exists;
use function assert;
use function count;
use function is_int;
use function is_string;

final class MapEmitter implements NodeEmitterInterface
{
    use WithOutputEmitterTrait;

    public function emit(AbstractNode $node): void
    {
        assert($node instanceof MapNode);
        $loc = $node->getStartSourceLocation();

        $this->outputEmitter->emitContextPrefix($node->getEnv(), $loc);
        $cached = $this->outputEmitter->emitConstantSlotPrefix($node, $loc);

        $locationLiteral = $this->locationLiteral($node);
        $defLocationMeta = $locationLiteral === null ? $this->defLocationMeta($node) : null;
        if ($locationLiteral !== null) {
            $this->emitLocationHelper($locationLiteral, $loc);
        } elseif ($defLocationMeta !== null) {
            $this->emitDefLocationMetaHelper($defLocationMeta, $loc);
        } else {
            $this->outputEmitter->emitStr('\Phel::map(', $loc);
            $this->emitEntries($node);
            $this->outputEmitter->emitStr(')', $loc);
        }

        $meta = $node->getLiteralMeta();
        if ($meta instanceof MapNode) {
            $this->outputEmitter->emitStr('->withMeta(', $loc);
            $this->outputEmitter->emitNode($meta);
            $this->outputEmitter->emitStr(')', $loc);
        }

        if ($cached) {
            $this->outputEmitter->emitConstantSlotSuffix($loc);
        }

        $this->outputEmitter->emitContextSuffix($node->getEnv(), $loc);
    }

    /**
     * Detect the `{:file <string> :line <int> :column <int>}` shape that
     * every `def`/`defn` synthesises for `:start-location` and
     * `:end-location`. When found, the emitter swaps the three-entry
     * `\Phel::map(\Phel::keyword("file"), …)` call for a single
     * `\Phel::location("file", line, col)` helper invocation — same map
     * at runtime, far less generated PHP per definition.
     *
     * @return array{file: string, line: int, column: int}|null
     */
    private function locationLiteral(MapNode $node): ?array
    {
        $entries = $node->getKeyValues();
        if (count($entries) !== 6) {
            return null;
        }

        $expected = ['file' => null, 'line' => null, 'column' => null];

        for ($i = 0; $i < 6; $i += 2) {
            $key = $entries[$i];
            $value = $entries[$i + 1];

            if (!$key instanceof LiteralNode || !$value instanceof LiteralNode) {
                return null;
            }

            $keyword = $key->getValue();
            if (!$keyword instanceof Keyword) {
                return null;
            }

            $name = $keyword->getName();
            if (!array_key_exists($name, $expected) || $expected[$name] !== null) {
                return null;
            }

            $expected[$name] = $value->getValue();
        }

        if (!is_string($expected['file']) || !is_int($expected['line']) || !is_int($expected['column'])) {
            return null;
        }

        return [
            'file' => $expected['file'],
            'line' => $expected['line'],
            'column' => $expected['column'],
        ];
    }

    /**
     * @param array{file: string, line: int, column: int} $location
     */
    private function emitLocationHelper(array $location, ?SourceLocation $loc): void
    {
        $this->outputEmitter->emitStr(
            '\Phel::location("'
            . PhpStringEscape::doubleQuoted($location['file'])
            . '", ' . $location['line']
            . ', ' . $location['column']
            . ')',
            $loc,
        );
    }

    /**
     * Detect a `def`/`defn` metadata map that carries both location-shaped
     * `:start-location` and `:end-location` entries, and collapse it to one
     * `\Phel::locationMeta(...)` call so def-heavy namespaces emit far less
     * PHP for a value-equal runtime map. Any further metadata (`:doc`,
     * `:private`, `:tag`, …) rides along as trailing key/value arguments;
     * map value and hash are key-order independent, so the runtime map stays
     * value-equal (only its iteration order shifts, which is immaterial —
     * Phel maps are unordered and metadata is read by key). Maps missing
     * either location entry fall through to the generic `\Phel::map(...)` path.
     *
     * @return array{start: array{file: string, line: int, column: int}, end: array{file: string, line: int, column: int}, extras: list<array{AbstractNode, AbstractNode}>}|null
     */
    private function defLocationMeta(MapNode $node): ?array
    {
        $entries = $node->getKeyValues();
        $entryCount = count($entries);
        if ($entryCount < 4 || $entryCount % 2 !== 0) {
            return null;
        }

        $locationsByKeyword = [];
        $extras = [];
        for ($i = 0; $i < $entryCount; $i += 2) {
            $key = $entries[$i];
            $value = $entries[$i + 1];

            $keyword = $key instanceof LiteralNode ? $key->getValue() : null;
            $name = $keyword instanceof Keyword ? $keyword->getName() : null;
            $location = $value instanceof MapNode ? $this->locationLiteral($value) : null;

            if ($location !== null
                && ($name === 'start-location' || $name === 'end-location')
                && !isset($locationsByKeyword[$name])
            ) {
                $locationsByKeyword[$name] = $location;
                continue;
            }

            $extras[] = [$key, $value];
        }

        $start = $locationsByKeyword['start-location'] ?? null;
        $end = $locationsByKeyword['end-location'] ?? null;
        if ($start === null || $end === null) {
            return null;
        }

        return ['start' => $start, 'end' => $end, 'extras' => $extras];
    }

    /**
     * @param array{start: array{file: string, line: int, column: int}, end: array{file: string, line: int, column: int}, extras: list<array{AbstractNode, AbstractNode}>} $meta
     */
    private function emitDefLocationMetaHelper(array $meta, ?SourceLocation $loc): void
    {
        $this->outputEmitter->emitStr('\Phel::locationMeta(', $loc);
        $this->emitLocationHelper($meta['start'], $loc);
        $this->outputEmitter->emitStr(', ', $loc);
        $this->emitLocationHelper($meta['end'], $loc);

        foreach ($meta['extras'] as [$key, $value]) {
            $this->outputEmitter->emitStr(', ', $loc);
            $this->outputEmitter->emitNode($key);
            $this->outputEmitter->emitStr(', ', $loc);
            $this->outputEmitter->emitNode($value);
        }

        $this->outputEmitter->emitStr(')', $loc);
    }

    private function emitEntries(MapNode $node): void
    {
        $keyValues = $node->getKeyValues();
        $countKeyValues = count($keyValues);

        if ($countKeyValues > 0) {
            $this->outputEmitter->increaseIndentLevel();
            $this->outputEmitter->emitLine();
        }

        for ($i = 0; $i < $countKeyValues; $i += 2) {
            $key = $keyValues[$i];
            $value = $keyValues[$i + 1];

            $this->outputEmitter->emitNode($key);
            $this->outputEmitter->emitStr(', ', $node->getStartSourceLocation());
            $this->outputEmitter->emitNode($value);
            if ($i < $countKeyValues - 2) {
                $this->outputEmitter->emitStr(',', $node->getStartSourceLocation());
            }

            $this->outputEmitter->emitLine();
        }

        if ($countKeyValues > 0) {
            $this->outputEmitter->decreaseIndentLevel();
        }
    }
}
