<?php

declare(strict_types=1);

namespace Phel\Formatter\Domain\Rules;

use Phel\Compiler\Domain\Lexer\Token;
use Phel\Compiler\Domain\Parser\ParserNode\InnerNodeInterface;
use Phel\Compiler\Domain\Parser\ParserNode\ListNode;
use Phel\Compiler\Domain\Parser\ParserNode\NodeInterface;
use Phel\Compiler\Domain\Parser\ParserNode\SymbolNode;
use Phel\Compiler\Domain\Parser\ParserNode\TriviaNodeInterface;
use Phel\Formatter\Domain\Rules\Pair\PairAligner;

use function in_array;

/**
 * Aligns key/value pairs inside recognized forms so values line up under a
 * common column. Two families are handled:
 *
 *   - Pair forms: cond, case, condp. Pairs follow a head offset; case/condp
 *     allow an odd trailing default clause.
 *   - Binding forms: let, loop, binding, if-let, when-let, if-some, when-some.
 *     The first value-child is a bracket vector of symbol/value pairs.
 *
 * Note: for / foreach / dofor use 3-tuple (binding :in coll) plus modifier
 * keywords and are intentionally excluded.
 */
final readonly class AlignPairsRule implements RuleInterface
{
    /**
     * @var array<string, int> map head symbol => number of leading value-nodes
     *                         to skip before pair clauses begin
     */
    private const array PAIR_FORMS = [
        'cond' => 1,
        'case' => 2,
        'condp' => 3,
    ];

    /** @var list<string> heads whose first value-child is a binding vector */
    private const array BINDING_FORMS = [
        'let',
        'loop',
        'binding',
        'if-let',
        'when-let',
        'if-some',
        'when-some',
    ];

    private PairAligner $aligner;

    public function __construct()
    {
        $this->aligner = new PairAligner();
    }

    public function transform(NodeInterface $node): NodeInterface
    {
        return $this->visit($node);
    }

    private function visit(NodeInterface $node): NodeInterface
    {
        if (!$node instanceof InnerNodeInterface) {
            return $node;
        }

        $children = [];
        foreach ($node->getChildren() as $child) {
            $children[] = $this->visit($child);
        }

        $node->replaceChildren($children);

        if ($node instanceof ListNode && $node->getTokenType() === Token::T_OPEN_PARENTHESIS) {
            $this->applyFormAlignment($node);
        }

        return $node;
    }

    private function applyFormAlignment(ListNode $node): void
    {
        $children = $node->getChildren();
        $headName = $this->headName($children);
        if ($headName === null) {
            return;
        }

        if (isset(self::PAIR_FORMS[$headName])) {
            $node->replaceChildren(
                $this->aligner->align(
                    $children,
                    self::PAIR_FORMS[$headName],
                    allowTrailingSingle: $headName !== 'cond',
                ),
            );
            return;
        }

        if (in_array($headName, self::BINDING_FORMS, true)) {
            $vector = $this->firstVectorChild($children);
            if ($vector instanceof ListNode) {
                $vector->replaceChildren(
                    $this->aligner->align($vector->getChildren(), skipValueCount: 0, allowTrailingSingle: false),
                );
            }
        }
    }

    /**
     * @param list<NodeInterface> $children
     */
    private function headName(array $children): ?string
    {
        foreach ($children as $child) {
            if ($child instanceof TriviaNodeInterface) {
                continue;
            }

            return $child instanceof SymbolNode
                ? $child->getValue()->getName()
                : null;
        }

        return null;
    }

    /**
     * @param list<NodeInterface> $children
     */
    private function firstVectorChild(array $children): ?ListNode
    {
        $seenHead = false;
        foreach ($children as $child) {
            if ($child instanceof TriviaNodeInterface) {
                continue;
            }

            if (!$seenHead) {
                $seenHead = true;
                continue;
            }

            return $child instanceof ListNode && $child->getTokenType() === Token::T_OPEN_BRACKET
                ? $child
                : null;
        }

        return null;
    }
}
