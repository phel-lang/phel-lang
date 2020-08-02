<?php

declare(strict_types=1);

namespace Phel\Emitter\NodeEmitter;

use Phel\Ast\DefStructNode;
use Phel\Ast\Node;
use Phel\Emitter;
use Phel\Emitter\NodeEmitter;
use Phel\Lang\Keyword;
use Phel\Munge;

final class DefStructEmitter implements NodeEmitter
{
    private Emitter $emitter;

    public function __construct(Emitter $emitter)
    {
        $this->emitter = $emitter;
    }

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
        $this->emitter->indentLevel++;

        // Constructor
        $this->emitter->emitStr('public function __construct(', $node->getStartSourceLocation());
        foreach ($node->getParams() as $i => $param) {
            $this->emitter->emitPhpVariable($param);

            if ($i < $paramCount - 1) {
                $this->emitter->emitStr(', ', $node->getStartSourceLocation());
            }
        }
        $this->emitter->emitLine(') {', $node->getStartSourceLocation());
        $this->emitter->indentLevel++;
        foreach ($node->getParams() as $i => $param) {
            $keyword = new Keyword($param->getName());
            $keyword->setStartLocation($node->getStartSourceLocation());

            $this->emitter->emitStr('$this->offsetSet(', $node->getStartSourceLocation());
            $this->emitter->emitScalarAndAbstractType($keyword);
            $this->emitter->emitStr(', ', $node->getStartSourceLocation());
            $this->emitter->emitPhpVariable($param);
            $this->emitter->emitLine(');', $node->getStartSourceLocation());
        }
        $this->emitter->indentLevel--;
        $this->emitter->emitLine('}', $node->getStartSourceLocation());

        // Get Allowed Keys Function
        $this->emitter->emitStr('public function getAllowedKeys(', $node->getStartSourceLocation());
        $this->emitter->emitLine('): array {', $node->getStartSourceLocation());
        $this->emitter->indentLevel++;
        $this->emitter->emitStr('return [', $node->getStartSourceLocation());
        foreach ($node->getParamsAsKeywords() as $i => $keyword) {
            $this->emitter->emitScalarAndAbstractType($keyword);

            if ($i < $paramCount - 1) {
                $this->emitter->emitStr(', ', $node->getStartSourceLocation());
            }
        }
        $this->emitter->emitLine('];', $node->getStartSourceLocation());
        $this->emitter->indentLevel--;
        $this->emitter->emitLine('}', $node->getStartSourceLocation());

        // End of class
        $this->emitter->indentLevel--;
        $this->emitter->emitLine('}', $node->getStartSourceLocation());
    }
}
