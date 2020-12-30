<?php

declare(strict_types=1);

namespace Phel\Compiler;

use Phel\Compiler\Parser\ParserNode\AtomNode;
use Phel\Compiler\Parser\ParserNode\ListNode;
use Phel\Compiler\Parser\ParserNode\MetaNode;
use Phel\Compiler\Parser\ParserNode\NodeInterface;
use Phel\Compiler\Parser\ParserNode\QuoteNode;
use Phel\Compiler\Parser\ParserNode\SymbolNode;
use Phel\Compiler\Parser\ParserNode\TriviaNodeInterface;
use Phel\Compiler\Parser\QuasiquoteTransformerInterface;
use Phel\Compiler\Parser\ReadModel\CodeSnippet;
use Phel\Compiler\Parser\ReadModel\ReaderResult;
use Phel\Exceptions\ReaderException;
use Phel\Lang\AbstractType;
use Phel\Lang\IMeta;
use Phel\Lang\Keyword;
use Phel\Lang\PhelArray;
use Phel\Lang\Symbol;
use Phel\Lang\Table;
use Phel\Lang\Tuple;

final class Reader implements ReaderInterface
{
    /** @var Symbol[]|null */
    private ?array $fnArgs = null;

    private QuasiquoteTransformerInterface $quasiquoteTransformer;

    public function __construct(QuasiquoteTransformerInterface $quasiquoteTransformer)
    {
        $this->quasiquoteTransformer = $quasiquoteTransformer;
    }

    /**
     * Reads the next expression from the token stream.
     *
     * If the token stream reaches the end, null is returned.
     *
     * @param NodeInterface $tokenStream The token stream to read
     *
     * @throws ReaderException
     */
    public function read(NodeInterface $parseTree): ReaderResult
    {
        if ($parseTree instanceof TriviaNodeInterface) {
            throw $this->buildReaderException('Can not read from whitespace or comments', $parseTree);
        }

        $ast = $this->readExpression($parseTree);

        return new ReaderResult(
            $ast,
            $this->getCodeSnippet($parseTree)
        );
    }

    /**
     * @throws ReaderException
     *
     * @return AbstractType|string|float|int|bool|null
     */
    public function readExpression(NodeInterface $node)
    {
        if ($node instanceof SymbolNode) {
            $symbol =  $this->readSymbol($node);
            $symbol->setStartLocation($node->getStartLocation());
            $symbol->setEndLocation($node->getEndLocation());

            return $symbol;
        }

        if ($node instanceof AtomNode) {
            $value = $node->getValue();

            if ($value instanceof AbstractType) {
                $value->setStartLocation($node->getStartLocation());
                $value->setEndLocation($node->getEndLocation());
            }

            return $value;
        }

        if ($node instanceof ListNode && $node->getTokenType() === Token::T_OPEN_PARENTHESIS) {
            return $this->readList($node);
        }

        if ($node instanceof ListNode && $node->getTokenType() === Token::T_OPEN_BRACKET) {
            return $this->readList($node, true);
        }

        if ($node instanceof ListNode && $node->getTokenType() === Token::T_ARRAY) {
            $tuple = $this->readList($node);
            $arr = new PhelArray($tuple->toArray());
            $arr->setStartLocation($tuple->getStartLocation());
            $arr->setEndLocation($tuple->getEndLocation());
            return $arr;
        }

        if ($node instanceof ListNode && $node->getTokenType() === Token::T_TABLE) {
            $tuple = $this->readList($node);
            if (count($tuple) % 2 === 1) {
                throw $this->buildReaderException('Tables must have an even number of parameters', $node);
            }
            $table = Table::fromKVArray($tuple->toArray());
            $table->setStartLocation($tuple->getStartLocation());
            $table->setEndLocation($tuple->getEndLocation());
            return $table;
        }

        if ($node instanceof ListNode && $node->getTokenType() === Token::T_FN) {
            $this->fnArgs = [];
            $body = $this->readList($node);

            if (!empty($this->fnArgs)) {
                $maxParams = max(array_keys($this->fnArgs));
                $params = [];
                for ($i = 1; $i <= $maxParams; $i++) {
                    if (isset($this->fnArgs[$i])) {
                        $params[] = Symbol::create($this->fnArgs[$i]->getName());
                    } else {
                        $params[] = Symbol::gen('__short_fn_undefined_');
                    }
                }

                if (isset($this->fnArgs[0])) {
                    $params[] = Symbol::create('&');
                    $params[] = Symbol::create($this->fnArgs[0]->getName());
                }
            } else {
                $params = [];
            }

            $this->fnArgs = null;
            return Tuple::create(Symbol::create(Symbol::NAME_FN), new Tuple($params, true), $body);
        }

        if ($node instanceof QuoteNode && $node->getTokenType() === Token::T_QUOTE) {
            return $this->readWrap($node, Symbol::NAME_QUOTE);
        }

        if ($node instanceof QuoteNode && $node->getTokenType() === Token::T_UNQUOTE) {
            return $this->readWrap($node, Symbol::NAME_UNQUOTE);
        }

        if ($node instanceof QuoteNode && $node->getTokenType() === Token::T_UNQUOTE_SPLICING) {
            return $this->readWrap($node, Symbol::NAME_UNQUOTE_SPLICING);
        }

        if ($node instanceof QuoteNode && $node->getTokenType() === Token::T_QUASIQUOTE) {
            return $this->readQuasiquote($node);
        }

        if ($node instanceof MetaNode) {
            return $this->readMeta($node);
        }

        throw $this->buildReaderException('Unterminated list', $node);
    }

    /**
     * @return AbstractType|string|float|int|bool|null
     */
    private function readQuasiquote(QuoteNode $node)
    {
        $expression = $this->readExpression($node->getExpression());
        $result = $this->quasiquoteTransformer->transform($expression);

        if ($result instanceof AbstractType) {
            $result->setStartLocation($node->getStartLocation());
            $result->setEndLocation($node->getEndLocation());
        }

        return $result;
    }

    /**
     * @return AbstractType|string|float|int|bool
     */
    private function readMeta(MetaNode $node)
    {
        $metaExpression = $node->getMetaNode();
        $objectExpression = $node->getObjectNode();

        $meta = $this->readExpression($metaExpression);
        if (is_string($meta) || $meta instanceof Symbol) {
            $meta = Table::fromKVs(new Keyword('tag'), $meta);
        } elseif ($meta instanceof Keyword) {
            $meta = Table::fromKVs($meta, true);
        } elseif (!$meta instanceof Table) {
            throw $this->buildReaderException('Metadata must be a Symbol, String, Keyword or Table', $node);
        }
        $object = $this->readExpression($objectExpression);

        if (!$object instanceof IMeta) {
            throw $this->buildReaderException('Metadata can only applied to classes that implement IMeta', $node);
        }

        $objMeta = $object->getMeta();
        foreach ($meta as $k => $v) {
            if ($k) {
                $objMeta[$k] = $v;
            }
        }
        $object->setMeta($objMeta);

        return $object;
    }

    private function readWrap(QuoteNode $node, string $wrapFn): Tuple
    {
        $expression = $this->readExpression($node->getExpression());

        $tuple = new Tuple([Symbol::create($wrapFn), $expression]);
        $tuple->setStartLocation($node->getStartLocation());
        $tuple->setEndLocation($node->getEndLocation());

        return $tuple;
    }

    private function readList(ListNode $node, bool $isUsingBrackets = false): Tuple
    {
        $acc = [];
        foreach ($node->getChildren() as $child) {
            if ($child instanceof TriviaNodeInterface) {
                continue;
            }

            $acc[] = $this->readExpression($child);
        }

        $tuple = new Tuple($acc, $isUsingBrackets);
        $tuple->setStartLocation($node->getStartLocation());
        $tuple->setEndLocation($node->getEndLocation());

        return $tuple;
    }

    private function readSymbol(SymbolNode $node): Symbol
    {
        if (!is_array($this->fnArgs)) {
            return $node->getValue();
        }

        $symbol = $node->getValue();
        $word = $symbol->getName();

        // Special case: We read an anonymous function
        if ($word === '$') {
            if (isset($this->fnArgs[1])) {
                return Symbol::create($this->fnArgs[1]->getName());
            }
            $sym = Symbol::gen('__short_fn_1_');
            $this->fnArgs[1] = $sym;
            return $sym;
        }

        if ($word === '$&') {
            if (isset($this->fnArgs[0])) {
                return Symbol::create($this->fnArgs[0]->getName());
            }
            $sym = Symbol::gen('__short_fn_rest_');
            $this->fnArgs[0] = $sym;
            return $sym;
        }

        if (preg_match('/\$([1-9][0-9]*)/', $word, $matches)) {
            $number = (int)$matches[1];
            if (isset($this->fnArgs[$number])) {
                return Symbol::create($this->fnArgs[$number]->getName());
            }
            $sym = Symbol::gen('__short_fn_' . $number . '_');
            $this->fnArgs[$number] = $sym;
            return $sym;
        }

        return $node->getValue();
    }

    /**
     * Create a CodeSnippet from a list of Tokens.
     *
     * @param NodeInterface $node The current node
     */
    private function getCodeSnippet(NodeInterface $node): CodeSnippet
    {
        return new CodeSnippet(
            $node->getStartLocation(),
            $node->getEndLocation(),
            $node->getCode()
        );
    }

    private function buildReaderException(string $message, NodeInterface $node): ReaderException
    {
        $codeSnippet = $this->getCodeSnippet($node);

        return new ReaderException(
            $message,
            $codeSnippet->getStartLocation(),
            $codeSnippet->getEndLocation(),
            $codeSnippet
        );
    }
}
