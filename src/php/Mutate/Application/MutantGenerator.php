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
use Phel\Shared\Parser\Node\ReaderConditionalNode;
use Phel\Shared\Parser\Node\ReaderCondSplicingNode;
use Phel\Shared\Parser\Node\StringNode;
use Phel\Shared\Parser\Node\Token;
use Phel\Shared\Parser\Node\TriviaNodeInterface;

use function array_slice;
use function count;
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
 * @phpstan-type SourceSite array{parent: InnerNodeInterface, index: int, child: NodeInterface, dropParent: InnerNodeInterface, dropIndex: int}
 * @phpstan-type Site array{parent: InnerNodeInterface, index: int, child: NodeInterface, context: InnerNodeInterface, contextIndex: int, sources: list<SourceSite>}
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
        $views = new SemanticViews();
        foreach ($tree->getChildren() as $form) {
            $sourceForm = $form;
            while ($form instanceof ReaderConditionalNode) {
                $form = $form->getSelected();
            }

            if (!$form instanceof ListNode) {
                continue;
            }

            if (!in_array($this->headOf($form), self::DEFINITION_HEADS, true)) {
                continue;
            }

            $definition = $this->definitionName($form);
            $originalForm = $sourceForm->getCode();
            foreach ($this->sitesOf($form) as $site) {
                $site['context'] = $this->semanticView($site['context'], $views);
                $child = $site['context']->getChildren()[$site['contextIndex']];
                foreach ($this->mutators as $mutator) {
                    foreach ($mutator->mutate($site['context'], $site['contextIndex'], $child) as $replacement) {
                        $restore = $this->applyReplacement($site, $replacement->children);
                        try {
                            $mutatedForm = $sourceForm->getCode();
                        } finally {
                            foreach ($restore as [$parent, $children]) {
                                $parent->replaceChildren($children);
                            }
                        }

                        if ($mutatedForm === $originalForm) {
                            continue;
                        }

                        $mutants[] = new Mutant(
                            $file,
                            $namespace,
                            $definition,
                            $child->getStartLocation()->getLine(),
                            $child->getStartLocation()->getColumn() + 1,
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
            while ($form instanceof ReaderConditionalNode) {
                $form = $form->getSelected();
            }

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
        $children = array_column($this->semanticSites($definition), 'child');
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
                $arityChildren = array_column($this->semanticSites($child), 'child');
                $firstSignificant = Nodes::firstSignificantIndex($arityChildren, 0);
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
        $index = Nodes::firstSignificantIndex($children, 0); // head
        $index = Nodes::firstSignificantIndex($children, $index + 1); // name
        $index = Nodes::firstSignificantIndex($children, $index + 1);

        while (isset($children[$index])) {
            $node = $children[$index];
            $isDocstring = $node instanceof StringNode;
            $isAttrMap = $node instanceof ListNode && $node->getTokenType() === Token::T_OPEN_BRACE;
            if (!$isDocstring && !$isAttrMap) {
                break;
            }

            $index = Nodes::firstSignificantIndex($children, $index + 1);
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
        foreach ($this->semanticSites($parent) as $position => $site) {
            $child = $site['child'];
            if ($position < $from) {
                continue;
            }

            if ($child instanceof TriviaNodeInterface) {
                continue;
            }

            $sites[] = $site;
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
        if ($node instanceof ReaderConditionalNode) {
            $selected = $node->getSelected();
            return [...$this->semanticSites($node), ...$this->descendantSites($selected)];
        }

        if ($node instanceof ReaderCondSplicingNode) {
            $selected = $node->getSelected();
            return $selected instanceof NodeInterface ? $this->descendantSites($selected) : [];
        }

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
     * @return list<NodeInterface>
     */
    private function significantChildren(InnerNodeInterface $node): array
    {
        $significant = [];
        foreach ($this->semanticSites($node) as $site) {
            if (!$site['child'] instanceof TriviaNodeInterface) {
                $significant[] = $site['child'];
            }
        }

        return $significant;
    }

    /**
     * @return list<Site>
     */
    private function semanticSites(InnerNodeInterface $parent): array
    {
        $sources = [];
        foreach ($parent->getChildren() as $index => $child) {
            if ($child instanceof ReaderConditionalNode) {
                foreach ($this->semanticSites($child) as $site) {
                    $sources[] = ['parent' => $site['parent'], 'index' => $site['index'], 'child' => $site['child'], 'dropParent' => $parent, 'dropIndex' => $index];
                }
            } elseif ($child instanceof ReaderCondSplicingNode) {
                $selected = $child->getSelected();
                while ($selected instanceof ReaderConditionalNode) {
                    $selected = $selected->getSelected();
                }

                if ($selected instanceof InnerNodeInterface) {
                    foreach ($this->semanticSites($selected) as $site) {
                        $sources[] = ['parent' => $site['parent'], 'index' => $site['index'], 'child' => $site['child'], 'dropParent' => $site['sources'][$site['contextIndex']]['dropParent'], 'dropIndex' => $site['sources'][$site['contextIndex']]['dropIndex']];
                    }
                }
            } else {
                $sources[] = ['parent' => $parent, 'index' => $index, 'child' => $child, 'dropParent' => $parent, 'dropIndex' => $index];
            }
        }

        $context = $parent instanceof ListNode
            ? new ListNode($parent->getTokenType(), $parent->getStartLocation(), $parent->getEndLocation(), array_column($sources, 'child'))
            : $parent;
        $sites = [];
        foreach ($sources as $position => $source) {
            $sites[] = ['parent' => $source['parent'], 'index' => $source['index'], 'child' => $source['child'], 'context' => $context, 'contextIndex' => $position, 'sources' => $sources];
        }

        return $sites;
    }

    private function semanticView(InnerNodeInterface $node, SemanticViews $views): InnerNodeInterface
    {
        $cached = $views->get($node);
        if ($cached instanceof InnerNodeInterface) {
            return $cached;
        }

        if (!$node instanceof ListNode) {
            return $node;
        }

        $children = [];
        foreach ($this->semanticSites($node) as $site) {
            $child = $site['child'];
            $children[] = $child instanceof ListNode ? $this->semanticView($child, $views) : $child;
        }

        $view = new ListNode($node->getTokenType(), $node->getStartLocation(), $node->getEndLocation(), $children);
        $views->put($node, $view);
        return $view;
    }

    /**
     * @param Site                $site
     * @param list<NodeInterface> $replacement
     *
     * @return list<array{InnerNodeInterface, list<NodeInterface>}>
     */
    private function applyReplacement(array $site, array $replacement): array
    {
        $original = $site['context']->getChildren();
        $first = 0;
        while (isset($original[$first], $replacement[$first]) && $original[$first] === $replacement[$first]) {
            ++$first;
        }

        $lastOriginal = count($original) - 1;
        $lastReplacement = count($replacement) - 1;
        while ($lastOriginal >= $first && $lastReplacement >= $first && $original[$lastOriginal] === $replacement[$lastReplacement]) {
            --$lastOriginal;
            --$lastReplacement;
        }

        $insert = array_slice($replacement, $first, $lastReplacement - $first + 1);
        $replacementNode = count($insert) === 1 ? $insert[0] : null;
        $edits = [];
        for ($index = $first; $index <= $lastOriginal; ++$index) {
            $source = $site['sources'][$index];
            if ($insert === [] && $source['child'] instanceof TriviaNodeInterface
                && $source['dropParent'] !== $site['sources'][$site['contextIndex']]['dropParent']
            ) {
                continue;
            }

            if ($replacementNode instanceof ListNode && $first === $lastOriginal
                && $source['child'] instanceof ListNode && $original[$index] instanceof ListNode
                && $source['child']->getTokenType() === $replacementNode->getTokenType()
                && $source['child']->getStartLocation() === $replacementNode->getStartLocation()
            ) {
                $nestedSources = $this->semanticSites($source['child']);
                $nestedSite = $nestedSources[0] ?? null;
                if ($nestedSite !== null) {
                    $nestedSite['context'] = $original[$index];
                    $nestedSite['contextIndex'] = 0;
                    foreach ($nestedSources as $position => $candidate) {
                        if (($original[$index]->getChildren()[$position] ?? null) !== ($replacementNode->getChildren()[$position] ?? null)) {
                            $nestedSite['contextIndex'] = $position;
                            break;
                        }
                    }

                    return $this->applyReplacement($nestedSite, $replacementNode->getChildren());
                }
            }

            $parent = $insert === [] ? $source['dropParent'] : $source['parent'];
            $targetIndex = $insert === [] ? $source['dropIndex'] : $source['index'];
            $id = spl_object_id($parent);
            $edits[$id] ??= ['parent' => $parent, 'children' => $parent->getChildren(), 'edits' => []];
            $edits[$id]['edits'][$targetIndex] = $index === $first ? $insert : [];
        }

        $restore = [];
        foreach ($edits as $edit) {
            $restore[] = [$edit['parent'], $edit['children']];
            $children = [];
            foreach ($edit['children'] as $index => $child) {
                array_push($children, ...($edit['edits'][$index] ?? [$child]));
            }

            $edit['parent']->replaceChildren($children);
        }

        return $restore;
    }
}
