<?php

declare(strict_types=1);

namespace Phel\Emitter\NodeEmitter;

use Phel\Ast\DefStructNode;
use Phel\Ast\Node;
use Phel\Emitter\NodeEmitter;
use Phel\Lang\Keyword;
use Phel\Munge;

final class DefStructEmitter implements NodeEmitter
{
    use WithEmitter;

    public function emit(Node $node): void
    {
        assert($node instanceof DefStructNode);

        $paramCount = count($node->getParams());
        $this->emitter->emitLine(
            'namespace ' . Munge::encodeNs($node->getNamespace()) . ';',
            $node->getStartSourceLocation()
        );
        $this->emitter->emitLine(
            'class ' . $this->emitter->munge($node->getName()->getName()) . ' extends \Phel\Lang\Struct {',
            $node->getStartSourceLocation()
        );
        $this->emitter->increaseIndentLevel();

        // Constructor
        $this->emitter->emitStr('public function __construct(', $node->getStartSourceLocation());
        foreach ($node->getParams() as $i => $param) {
            $this->emitter->emitPhpVariable($param);

            if ($i < $paramCount - 1) {
                $this->emitter->emitStr(', ', $node->getStartSourceLocation());
            }
        }
        $this->emitter->emitLine(') {', $node->getStartSourceLocation());
        $this->emitter->increaseIndentLevel();
        foreach ($node->getParams() as $i => $param) {
            $keyword = new Keyword($param->getName());
            $keyword->setStartLocation($node->getStartSourceLocation());

            $this->emitter->emitStr('$this->offsetSet(', $node->getStartSourceLocation());
            $this->emitter->emitLiteral($keyword);
            $this->emitter->emitStr(', ', $node->getStartSourceLocation());
            $this->emitter->emitPhpVariable($param);
            $this->emitter->emitLine(');', $node->getStartSourceLocation());
        }
        $this->emitter->decreaseIndentLevel();
        $this->emitter->emitLine('}', $node->getStartSourceLocation());

        // Get Allowed Keys Function
        $this->emitter->emitStr('public function getAllowedKeys(', $node->getStartSourceLocation());
        $this->emitter->emitLine('): array {', $node->getStartSourceLocation());
        $this->emitter->increaseIndentLevel();
        $this->emitter->emitStr('return [', $node->getStartSourceLocation());
        foreach ($node->getParamsAsKeywords() as $i => $keyword) {
            $this->emitter->emitLiteral($keyword);

            if ($i < $paramCount - 1) {
                $this->emitter->emitStr(', ', $node->getStartSourceLocation());
            }
        }
        $this->emitter->emitLine('];', $node->getStartSourceLocation());
        $this->emitter->decreaseIndentLevel();
        $this->emitter->emitLine('}', $node->getStartSourceLocation());

        // End of class
        $this->emitter->decreaseIndentLevel();
        $this->emitter->emitLine('}', $node->getStartSourceLocation());
    }
}
