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
    use WithOutputEmitter;

    public function emit(Node $node): void
    {
        assert($node instanceof DefStructNode);

        $paramCount = count($node->getParams());
        $this->outputEmitter->emitLine(
            'namespace ' . Munge::encodeNs($node->getNamespace()) . ';',
            $node->getStartSourceLocation()
        );
        $this->outputEmitter->emitLine(
            'class ' . $this->outputEmitter->munge($node->getName()->getName()) . ' extends \Phel\Lang\Struct {',
            $node->getStartSourceLocation()
        );
        $this->outputEmitter->increaseIndentLevel();

        // Constructor
        $this->outputEmitter->emitStr('public function __construct(', $node->getStartSourceLocation());
        foreach ($node->getParams() as $i => $param) {
            $this->outputEmitter->emitPhpVariable($param);

            if ($i < $paramCount - 1) {
                $this->outputEmitter->emitStr(', ', $node->getStartSourceLocation());
            }
        }
        $this->outputEmitter->emitLine(') {', $node->getStartSourceLocation());
        $this->outputEmitter->increaseIndentLevel();
        foreach ($node->getParams() as $i => $param) {
            $keyword = new Keyword($param->getName());
            $keyword->setStartLocation($node->getStartSourceLocation());

            $this->outputEmitter->emitStr('$this->offsetSet(', $node->getStartSourceLocation());
            $this->outputEmitter->emitLiteral($keyword);
            $this->outputEmitter->emitStr(', ', $node->getStartSourceLocation());
            $this->outputEmitter->emitPhpVariable($param);
            $this->outputEmitter->emitLine(');', $node->getStartSourceLocation());
        }
        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitLine('}', $node->getStartSourceLocation());

        // Get Allowed Keys Function
        $this->outputEmitter->emitStr('public function getAllowedKeys(', $node->getStartSourceLocation());
        $this->outputEmitter->emitLine('): array {', $node->getStartSourceLocation());
        $this->outputEmitter->increaseIndentLevel();
        $this->outputEmitter->emitStr('return [', $node->getStartSourceLocation());
        foreach ($node->getParamsAsKeywords() as $i => $keyword) {
            $this->outputEmitter->emitLiteral($keyword);

            if ($i < $paramCount - 1) {
                $this->outputEmitter->emitStr(', ', $node->getStartSourceLocation());
            }
        }
        $this->outputEmitter->emitLine('];', $node->getStartSourceLocation());
        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitLine('}', $node->getStartSourceLocation());

        // End of class
        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitLine('}', $node->getStartSourceLocation());
    }
}
