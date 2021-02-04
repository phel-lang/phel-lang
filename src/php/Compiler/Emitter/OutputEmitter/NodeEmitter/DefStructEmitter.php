<?php

declare(strict_types=1);

namespace Phel\Compiler\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Analyzer\Ast\DefStructNode;
use Phel\Compiler\Emitter\OutputEmitter\NodeEmitterInterface;
use Phel\Lang\Keyword;

final class DefStructEmitter implements NodeEmitterInterface
{
    use WithOutputEmitterTrait;

    public function emit(AbstractNode $node): void
    {
        assert($node instanceof DefStructNode);

        $this->emitClassBegin($node);
        $this->emitConstructor($node);
        $this->emitGetAllowedKeysFunction($node);
        $this->emitClassEnd($node);
    }

    private function emitClassBegin(DefStructNode $node): void
    {
        $this->outputEmitter->emitLine(
            'namespace ' . $this->outputEmitter->mungeEncodeNs($node->getNamespace()) . ';',
            $node->getStartSourceLocation()
        );
        $this->outputEmitter->emitLine(
            'class ' . $this->outputEmitter->mungeEncode($node->getName()->getName()) . ' extends \Phel\Lang\AbstractStruct {',
            $node->getStartSourceLocation()
        );
        $this->outputEmitter->increaseIndentLevel();
    }

    private function emitConstructor(DefStructNode $node): void
    {
        $paramCount = count($node->getParams());
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
    }

    private function emitGetAllowedKeysFunction(DefStructNode $node): void
    {
        $paramCount = count($node->getParams());

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
    }

    private function emitClassEnd(DefStructNode $node): void
    {
        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitLine('}', $node->getStartSourceLocation());
    }
}
