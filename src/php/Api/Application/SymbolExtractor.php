<?php

declare(strict_types=1);

namespace Phel\Api\Application;

use Phel\Api\Transfer\Definition;
use Phel\Api\Transfer\Location;
use Phel\Compiler\Domain\Parser\Exceptions\AbstractParserException;
use Phel\Compiler\Domain\Parser\ParserNode\NodeInterface;
use Phel\Compiler\Domain\Parser\ParserNode\TriviaNodeInterface;
use Phel\Compiler\Domain\Reader\Exceptions\ReaderException;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\Keyword;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;
use Phel\Lang\TypeInterface;
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
     * @return array{
     *     namespace: string,
     *     definitions: list<Definition>,
     *     references: array<string, list<Location>>,
     * }
     */
    public function extract(string $source, string $uri): array
    {
        $namespace = '';
        $definitions = [];
        /** @var array<string, list<Location>> $references */
        $references = [];

        try {
            $tokenStream = $this->compilerFacade->lexString($source, $uri);
            while (true) {
                try {
                    $parseTree = $this->compilerFacade->parseNext($tokenStream);
                } catch (AbstractParserException) {
                    break;
                }

                if (!$parseTree instanceof NodeInterface) {
                    break;
                }

                if ($parseTree instanceof TriviaNodeInterface) {
                    continue;
                }

                try {
                    $readerResult = $this->compilerFacade->read($parseTree);
                } catch (ReaderException) {
                    continue;
                }

                $form = $readerResult->getAst();

                if ($namespace === '') {
                    $maybeNs = $this->tryExtractNamespace($form);
                    if ($maybeNs !== '') {
                        $namespace = $maybeNs;
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

    private function tryExtractNamespace(mixed $form): string
    {
        if (!$form instanceof PersistentListInterface || count($form) === 0) {
            return '';
        }

        $first = $form->get(0);
        if (!$first instanceof Symbol || $first->getName() !== Symbol::NAME_NS) {
            return '';
        }

        if (count($form) < 2) {
            return '';
        }

        $name = $form->get(1);
        if (!$name instanceof Symbol) {
            return '';
        }

        return $name->getFullName();
    }

    /**
     * @param TypeInterface|null|scalar $form
     */
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
        );
    }

    /**
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
     * @param mixed $form
     *
     * @psalm-param K|T|TValue|V $form
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
