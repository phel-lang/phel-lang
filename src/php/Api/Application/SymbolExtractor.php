<?php

declare(strict_types=1);

namespace Phel\Api\Application;

use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\Keyword;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;
use Phel\Lang\TypeInterface;
use Phel\Shared\Api\Definition;
use Phel\Shared\Api\Location;
use Phel\Shared\Facade\CompilerFacadeInterface;

use Throwable;

use function count;
use function in_array;
use function is_string;

/**
 * Reads a single .phel source string and extracts:
 * - the primary namespace (if any),
 * - a list of top-level Definitions,
 * - reference Locations (every symbol usage inside bodies).
 *
 * PARTIAL BY DESIGN. Every result is a lower bound, never a guarantee:
 *
 * - the source is read best-effort (`readFormsBestEffort`), so a buffer that
 *   does not parse yields the forms read up to the failure and nothing after;
 * - any `Throwable` mid-extraction is swallowed and whatever was collected so
 *   far is returned;
 * - only literal top-level `def*` forms are seen, so definitions produced by a
 *   macro expansion are invisible.
 *
 * Callers must treat "absent" as "not found by this pass", not as "does not
 * exist": this feeds editor tooling (completion, jump-to-def, lint), where a
 * missing entry degrades a feature and a thrown exception would kill the run.
 *
 * @internal
 */
final readonly class SymbolExtractor
{
    private const array DEFINITION_FORMS = [
        'def' => Definition::KIND_DEF,
        'def-' => Definition::KIND_DEF,
        'defn' => Definition::KIND_DEFN,
        'defn-' => Definition::KIND_DEFN,
        'defmacro' => Definition::KIND_DEFMACRO,
        'defmacro-' => Definition::KIND_DEFMACRO,
        'defstruct' => Definition::KIND_DEFSTRUCT,
        'defstruct*' => Definition::KIND_DEFSTRUCT,
        'definterface' => Definition::KIND_DEFINTERFACE,
        'defprotocol' => Definition::KIND_DEFPROTOCOL,
        'defexception' => Definition::KIND_DEFEXCEPTION,
    ];

    public function __construct(
        private CompilerFacadeInterface $compilerFacade,
    ) {}

    /**
     * Top-level definitions in a single source buffer (document symbols).
     *
     * @return list<Definition> possibly incomplete; see the class docblock
     */
    public function definitionsOf(string $source, string $uri): array
    {
        return $this->extract($source, $uri)['definitions'];
    }

    /**
     * Never throws and never signals partial success: an unparseable buffer is
     * indistinguishable from a buffer with nothing in it.
     *
     * @return array{
     *     namespace: string,
     *     namespaceLocation: Location|null,
     *     definitions: list<Definition>,
     *     references: array<string, list<Location>>,
     * } possibly incomplete; see the class docblock
     */
    public function extract(string $source, string $uri): array
    {
        $namespace = '';
        $namespaceLocation = null;
        $definitions = [];
        /** @var array<string, list<Location>> $references */
        $references = [];

        try {
            foreach ($this->compilerFacade->readFormsBestEffort($source, $uri) as $form) {
                if ($namespace === '') {
                    $namespaceSymbol = $this->tryExtractNamespace($form);
                    if ($namespaceSymbol instanceof Symbol) {
                        $namespace = $namespaceSymbol->getFullName();
                        $start = $namespaceSymbol->getStartLocation();
                        $end = $namespaceSymbol->getEndLocation();
                        $namespaceLocation = new Location(
                            $uri,
                            $start?->getLine() ?? 0,
                            $start?->getColumn() ?? 0,
                            $end?->getLine() ?? 0,
                            $end?->getColumn() ?? 0,
                        );
                        continue;
                    }
                }

                $definition = $this->tryExtractDefinition($form, $namespace, $uri);
                if ($definition instanceof Definition) {
                    $definitions[] = $definition;
                }

                $this->collectSymbolReferences($form, $uri, $namespace, $references);
            }
        } catch (Throwable) {
            // Best-effort extractor: if anything fails we just return what we have.
        }

        return [
            'namespace' => $namespace,
            'namespaceLocation' => $namespaceLocation,
            'definitions' => $definitions,
            'references' => $references,
        ];
    }

    /**
     * Helper so tests/callers with a raw form (already read) can pull a definition directly.
     *
     * @internal
     */
    public function definitionFromForm(
        TypeInterface|string|float|int|bool|null $form,
        string $namespace,
        string $uri,
    ): ?Definition {
        return $this->tryExtractDefinition($form, $namespace, $uri);
    }

    private function tryExtractNamespace(mixed $form): ?Symbol
    {
        if (!$form instanceof PersistentListInterface || count($form) === 0) {
            return null;
        }

        $first = $form->get(0);
        if (!$first instanceof Symbol || $first->getName() !== Symbol::NAME_NS) {
            return null;
        }

        if (count($form) < 2) {
            return null;
        }

        $name = $form->get(1);
        if (!$name instanceof Symbol) {
            return null;
        }

        return $name;
    }

    private function tryExtractDefinition(mixed $form, string $namespace, string $uri): ?Definition
    {
        if (!$form instanceof PersistentListInterface || count($form) === 0) {
            return null;
        }

        $first = $form->get(0);
        if (!$first instanceof Symbol) {
            return null;
        }

        $formName = $first->getName();
        if (!isset(self::DEFINITION_FORMS[$formName])) {
            return null;
        }

        if (count($form) < 2) {
            return null;
        }

        $name = $form->get(1);
        if (!$name instanceof Symbol) {
            return null;
        }

        $start = $name->getStartLocation() ?? $first->getStartLocation();

        return new Definition(
            namespace: $namespace,
            name: $name->getName(),
            uri: $uri,
            line: $start?->getLine() ?? 0,
            col: $start?->getColumn() ?? 0,
            kind: self::DEFINITION_FORMS[$formName],
            signature: $this->extractSignature($form, $formName),
            docstring: $this->extractDocstring($form, $formName),
            private: $this->isPrivate($form, $formName),
            deprecated: $this->extractDeprecation($form, $name),
        );
    }

    /**
     * Reads the `:deprecated` entry of the definition's metadata, either
     * attached to the name (`^:deprecated foo`) or in the metadata map. A
     * string value is the reason, `true` degrades to the literal
     * `'deprecated'`. Everything else (including the keyword merely being
     * *mentioned* in a docstring) means "not deprecated".
     *
     * @param PersistentListInterface<mixed> $form
     */
    private function extractDeprecation(PersistentListInterface $form, Symbol $name): string
    {
        $fromName = $this->deprecationValue($name->getMeta());
        if ($fromName !== '') {
            return $fromName;
        }

        $size = count($form);
        for ($i = 2; $i < $size; ++$i) {
            $child = $form->get($i);
            if ($child instanceof PersistentVectorInterface) {
                break;
            }

            if (!$child instanceof PersistentMapInterface) {
                continue;
            }

            $fromMap = $this->deprecationValue($child);
            if ($fromMap !== '') {
                return $fromMap;
            }
        }

        return '';
    }

    /**
     * @param PersistentMapInterface<mixed, mixed>|null $meta
     */
    private function deprecationValue(?PersistentMapInterface $meta): string
    {
        if (!$meta instanceof PersistentMapInterface) {
            return '';
        }

        $value = $meta->find(Keyword::create('deprecated'));
        if (is_string($value) && $value !== '') {
            return $value;
        }

        return $value === true ? 'deprecated' : '';
    }

    /**
     * @param PersistentListInterface<mixed> $form
     *
     * @return list<string>
     */
    private function extractSignature(PersistentListInterface $form, string $formName): array
    {
        if (count($form) < 3) {
            return [];
        }

        $arities = [];
        $counter = count($form);
        // Scan for either a vector (single arity) or nested lists (multi-arity)
        for ($i = 2; $i < $counter; ++$i) {
            $child = $form->get($i);
            if ($child instanceof PersistentVectorInterface) {
                $arities[] = $this->vectorToSignature($child);
                break;
            }

            if ($child instanceof PersistentListInterface) {
                $head = count($child) > 0 ? $child->get(0) : null;
                if ($head instanceof PersistentVectorInterface) {
                    $arities[] = $this->vectorToSignature($head);
                }
            }
        }

        if ($arities === [] && isset(self::DEFINITION_FORMS[$formName]) && self::DEFINITION_FORMS[$formName] === Definition::KIND_DEF) {
            // plain def has no param list
            return [];
        }

        return $arities;
    }

    /**
     * @param PersistentVectorInterface<mixed> $vector
     */
    private function vectorToSignature(PersistentVectorInterface $vector): string
    {
        $parts = [];
        foreach ($vector as $item) {
            if ($item instanceof Symbol) {
                $parts[] = $item->getName();
            } elseif ($item instanceof Keyword) {
                $parts[] = ':' . $item->getName();
            } elseif (is_string($item)) {
                $parts[] = '"' . $item . '"';
            }
        }

        return '[' . implode(' ', $parts) . ']';
    }

    /**
     * @param PersistentListInterface<mixed> $form
     */
    private function extractDocstring(PersistentListInterface $form, string $formName): string
    {
        if (!in_array(self::DEFINITION_FORMS[$formName] ?? '', [
            Definition::KIND_DEFN,
            Definition::KIND_DEFMACRO,
        ], true)) {
            return '';
        }

        if (count($form) < 3) {
            return '';
        }

        $candidate = $form->get(2);
        if (is_string($candidate)) {
            return $candidate;
        }

        return '';
    }

    /**
     * @param PersistentListInterface<mixed> $form
     */
    private function isPrivate(PersistentListInterface $form, string $formName): bool
    {
        if (str_ends_with($formName, '-')) {
            return true;
        }

        if (count($form) < 3) {
            return false;
        }

        $counter = count($form);

        // Look for metadata map attached between name and value
        for ($i = 2; $i < $counter; ++$i) {
            $child = $form->get($i);
            if ($child instanceof PersistentMapInterface) {
                $priv = $child->find(Keyword::create('private'));
                if ($priv === true) {
                    return true;
                }
            } elseif ($child instanceof PersistentVectorInterface) {
                break;
            }
        }

        return false;
    }

    /**
     * @param array<string, list<Location>> $references
     */
    private function collectSymbolReferences(mixed $form, string $uri, string $namespace, array &$references): void
    {
        if ($form instanceof Symbol) {
            $full = $form->getFullName();
            if ($full === '' || $full === '/') {
                return;
            }

            $location = $form->getStartLocation();
            if (!$location instanceof SourceLocation) {
                return;
            }

            $key = $this->refKey($form, $namespace);
            if ($key === null) {
                return;
            }

            $references[$key][] = new Location(
                uri: $uri,
                line: $location->getLine(),
                col: $location->getColumn(),
            );

            return;
        }

        if ($form instanceof PersistentListInterface
            || $form instanceof PersistentVectorInterface
        ) {
            foreach ($form as $child) {
                $this->collectSymbolReferences($child, $uri, $namespace, $references);
            }

            return;
        }

        if ($form instanceof PersistentMapInterface) {
            foreach ($form as $k => $v) {
                $this->collectSymbolReferences($k, $uri, $namespace, $references);
                $this->collectSymbolReferences($v, $uri, $namespace, $references);
            }
        }
    }

    /**
     * Build the "ns/name" reference key for a symbol:
     * - if it's qualified (foo/bar) use it as-is,
     * - otherwise anchor it to the current file namespace (so same-file references resolve).
     */
    private function refKey(Symbol $sym, string $namespace): ?string
    {
        $ns = $sym->getNamespace();
        $name = $sym->getName();

        if ($name === '' || !$this->isPlainIdentifier($name)) {
            return null;
        }

        if ($ns !== null && $ns !== '') {
            return $ns . '/' . $name;
        }

        if ($namespace === '') {
            return $name;
        }

        return $namespace . '/' . $name;
    }

    private function isPlainIdentifier(string $name): bool
    {
        // Ignore purely syntactic markers (`&`, `.`, etc.) that appear in fn params.
        return $name !== '&' && $name !== '.';
    }
}
