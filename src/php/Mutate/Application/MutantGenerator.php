<?php

declare(strict_types=1);

namespace Phel\Mutate\Application;

use Phel\Mutate\Domain\Mutant;
use Phel\Mutate\Domain\Mutator\MutatorInterface;
use Phel\Mutate\Domain\Mutator\Nodes;
use Phel\Shared\Facade\CompilerFacadeInterface;
use Phel\Shared\Parser\Node\FileNode;
use Phel\Shared\Parser\Node\InnerNodeInterface;
use Phel\Shared\Parser\Node\ListNode;
use Phel\Shared\Parser\Node\MetaNode;
use Phel\Shared\Parser\Node\NodeInterface;
use Phel\Shared\Parser\Node\QuoteNode;
use Phel\Shared\Parser\Node\StringNode;
use Phel\Shared\Parser\Node\Token;
use Phel\Shared\Parser\Node\TriviaNodeInterface;

use function file_get_contents;
use function in_array;

/**
 * Walks a file's parse tree and yields every mutant the configured mutators
 * can produce inside `defn` / `defn-` bodies. Each mutant is materialised by
 * swapping one child list in place, re-emitting the whole top-level form
 * through `getCode()`, and putting the original children back, so the tree
 * is untouched between sites and the mutated source differs from the
 * original by exactly the mutation.
 *
 * Not mutated on purpose: the definition head and name, docstring and
 * attribute map, parameter vectors, anything under a quote or quasiquote
 * (data, not code), and `def` / `defmacro` forms.
 *
 * @phpstan-type Site array{parent: ListNode, index: int, child: NodeInterface}
 *
 * @internal
 */
final readonly class MutantGenerator
{
    private const array DEFINITION_HEADS = ['defn', 'defn-'];

    private const array NAMESPACE_HEADS = ['ns', 'in-ns'];

    private const string DEFAULT_NAMESPACE = 'user';

    /**
     * @param list<MutatorInterface> $mutators
     */
    public function __construct(
        private CompilerFacadeInterface $compilerFacade,
        private array $mutators,
    ) {}

    /**
     * Every mutant of every readable file, in file order.
     *
     * @param list<string> $files
     *
     * @return list<Mutant>
     */
    public function generateFiles(array $files): array
    {
        $mutants = [];
        foreach ($files as $file) {
            $source = file_get_contents($file);
            if ($source === false) {
                continue;
            }

            foreach ($this->generate($file, $source) as $mutant) {
                $mutants[] = $mutant;
            }
        }

        return $mutants;
    }

    /**
     * @return list<Mutant>
     */
    public function generate(string $file, string $source): array
    {
        $tree = $this->compilerFacade->parseAll($this->compilerFacade->lexString($source, $file));
        $namespace = $this->namespaceOf($tree);

        $mutants = [];
        foreach ($tree->getChildren() as $form) {
            if (!$form instanceof ListNode) {
                continue;
            }

            if (!in_array($this->headOf($form), self::DEFINITION_HEADS, true)) {
                continue;
            }

            $definition = $this->definitionName($form);
            $originalForm = $form->getCode();
            foreach ($this->sitesOf($form) as ['parent' => $parent, 'index' => $index, 'child' => $child]) {
                foreach ($this->mutators as $mutator) {
                    foreach ($mutator->mutate($parent, $index, $child) as $replacement) {
                        $originalChildren = $parent->getChildren();
                        $parent->replaceChildren($replacement->children);
                        $mutatedForm = $form->getCode();
                        $parent->replaceChildren($originalChildren);

                        if ($mutatedForm === $originalForm) {
                            continue;
                        }

                        $mutants[] = new Mutant(
                            $file,
                            $namespace,
                            $definition,
                            $child->getStartLocation()->getLine(),
                            $child->getStartLocation()->getColumn(),
                            $form->getStartLocation()->getLine(),
                            $mutator->id(),
                            $replacement->description,
                            $originalForm,
                            $mutatedForm,
                        );
                    }
                }
            }
        }

        return $mutants;
    }

    /**
     * The namespace the file's definitions land in: `(ns name ...)`, or
     * `(in-ns name)` for a secondary file that joins one (`phel.core` is
     * split that way), where the name may be written quoted.
     */
    private function namespaceOf(FileNode $tree): string
    {
        foreach ($tree->getChildren() as $form) {
            if (!$form instanceof ListNode) {
                continue;
            }
            if (!in_array($this->headOf($form), self::NAMESPACE_HEADS, true)) {
                continue;
            }
            $significant = $this->significantChildren($form);
            $nameNode = $significant[1] ?? null;
            if ($nameNode instanceof QuoteNode) {
                $nameNode = $this->significantChildren($nameNode)[0] ?? null;
            }

            $name = $nameNode === null ? null : Nodes::symbolName($nameNode);

            return $name ?? self::DEFAULT_NAMESPACE;
        }

        return self::DEFAULT_NAMESPACE;
    }

    private function headOf(ListNode $form): ?string
    {
        if ($form->getTokenType() !== Token::T_OPEN_PARENTHESIS) {
            return null;
        }

        $significant = $this->significantChildren($form);

        return isset($significant[0]) ? Nodes::symbolName($significant[0]) : null;
    }

    private function definitionName(ListNode $form): string
    {
        $significant = $this->significantChildren($form);
        $nameNode = $significant[1] ?? null;
        if ($nameNode instanceof MetaNode) {
            $nameNode = $this->significantChildren($nameNode)[1] ?? null;
        }

        return $nameNode === null ? '' : (Nodes::symbolName($nameNode) ?? '');
    }

    /**
     * Every (parent, index, child) inside the body of a definition, in
     * source order. The body starts after the name, an optional docstring
     * and an optional attribute map; a single-arity body follows its
     * parameter vector, a multi-arity body is the tail of each `([params]
     * ...)` list.
     *
     * @return list<Site>
     */
    private function sitesOf(ListNode $definition): array
    {
        $children = $definition->getChildren();
        $bodyStart = $this->bodyStartIndex($children);
        $params = $children[$bodyStart] ?? null;

        if ($params instanceof ListNode && $params->getTokenType() === Token::T_OPEN_BRACKET) {
            return $this->bodySites($definition, $bodyStart + 1);
        }

        // Multi-arity: every `([params] body...)` list contributes its own body.
        $sites = [];
        foreach ($children as $index => $child) {
            if ($index < $bodyStart) {
                continue;
            }

            if ($child instanceof TriviaNodeInterface) {
                continue;
            }

            if ($child instanceof ListNode && $child->getTokenType() === Token::T_OPEN_PARENTHESIS) {
                $arityChildren = $child->getChildren();
                $firstSignificant = $this->firstSignificantIndex($arityChildren, 0);
                foreach ($this->bodySites($child, $firstSignificant + 1) as $site) {
                    $sites[] = $site;
                }
            }
        }

        return $sites;
    }

    /**
     * Index of the parameter vector (or the first arity list): after the
     * head, the name, and any docstring / attribute map that follows the name.
     *
     * @param list<NodeInterface> $children
     */
    private function bodyStartIndex(array $children): int
    {
        $index = $this->firstSignificantIndex($children, 0); // head
        $index = $this->firstSignificantIndex($children, $index + 1); // name
        $index = $this->firstSignificantIndex($children, $index + 1);

        while (isset($children[$index])) {
            $node = $children[$index];
            $isDocstring = $node instanceof StringNode;
            $isAttrMap = $node instanceof ListNode && $node->getTokenType() === Token::T_OPEN_BRACE;
            if (!$isDocstring && !$isAttrMap) {
                break;
            }

            $index = $this->firstSignificantIndex($children, $index + 1);
        }

        return $index;
    }

    /**
     * Sites for the body forms of `$parent` from `$from` on, and everything
     * inside them.
     *
     * @return list<Site>
     */
    private function bodySites(ListNode $parent, int $from): array
    {
        $sites = [];
        foreach ($parent->getChildren() as $index => $child) {
            if ($index < $from) {
                continue;
            }

            if ($child instanceof TriviaNodeInterface) {
                continue;
            }

            $sites[] = ['parent' => $parent, 'index' => $index, 'child' => $child];
            foreach ($this->descendantSites($child) as $site) {
                $sites[] = $site;
            }
        }

        return $sites;
    }

    /**
     * @return list<Site>
     */
    private function descendantSites(NodeInterface $node): array
    {
        if ($node instanceof QuoteNode
            && in_array($node->getTokenType(), [Token::T_QUOTE, Token::T_QUASIQUOTE], true)
        ) {
            return [];
        }

        if ($node instanceof MetaNode) {
            // `^meta form`: the metadata is not code; walk the form only.
            $sites = [];
            foreach ($node->getChildren() as $index => $child) {
                if ($index === 0) {
                    continue;
                }

                foreach ($this->descendantSites($child) as $site) {
                    $sites[] = $site;
                }
            }

            return $sites;
        }

        if (!$node instanceof ListNode) {
            return [];
        }

        return $this->bodySites($node, 0);
    }

    /**
     * @param list<NodeInterface> $children
     */
    private function firstSignificantIndex(array $children, int $from): int
    {
        $index = $from;
        while (isset($children[$index]) && $children[$index] instanceof TriviaNodeInterface) {
            ++$index;
        }

        return $index;
    }

    /**
     * @return list<NodeInterface>
     */
    private function significantChildren(InnerNodeInterface $node): array
    {
        $significant = [];
        foreach ($node->getChildren() as $child) {
            if (!$child instanceof TriviaNodeInterface) {
                $significant[] = $child;
            }
        }

        return $significant;
    }
}
