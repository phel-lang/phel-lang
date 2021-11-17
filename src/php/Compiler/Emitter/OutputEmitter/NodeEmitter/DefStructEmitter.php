<?php

declare(strict_types=1);

namespace Phel\Compiler\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Analyzer\Ast\DefStructInterface;
use Phel\Compiler\Analyzer\Ast\DefStructMethod;
use Phel\Compiler\Analyzer\Ast\DefStructNode;
use Phel\Compiler\Emitter\OutputEmitter\NodeEmitterInterface;
use Phel\Compiler\Emitter\OutputEmitterInterface;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;

final class DefStructEmitter implements NodeEmitterInterface
{
    private OutputEmitterInterface $outputEmitter;
    private MethodEmitter $methodEmitter;

    public function __construct(OutputEmitterInterface $emitter, MethodEmitter $methodEmitter)
    {
        $this->outputEmitter = $emitter;
        $this->methodEmitter = $methodEmitter;
    }

    public function emit(AbstractNode $node): void
    {
        assert($node instanceof DefStructNode);

        $this->emitClassBegin($node);
        $this->outputEmitter->emitLine();
        $this->emitAllowedKeys($node);
        $this->outputEmitter->emitLine();
        $this->emitProperties($node);
        $this->outputEmitter->emitLine();
        $this->emitConstructor($node);
        foreach ($node->getInterfaces() as $interface) {
            $this->emitInterfaceImplementation($interface);
        }
        //$this->emitGetAllowedKeysFunction($node);
        $this->emitClassEnd($node);
    }

    private function emitClassBegin(DefStructNode $node): void
    {
        if ($this->outputEmitter->getOptions()->isStatementEmitMode()) {
            $this->outputEmitter->emitLine(
                'namespace ' . $this->outputEmitter->mungeEncodeNs($node->getNamespace()) . ';',
                $node->getStartSourceLocation()
            );
        }
        $this->outputEmitter->emitStr(
            'class ' . $this->outputEmitter->mungeEncode($node->getName()->getName()) . ' extends \Phel\Lang\Collections\Struct\AbstractPersistentStruct',
            $node->getStartSourceLocation()
        );

        if (count($node->getInterfaces()) > 0) {
            $this->outputEmitter->emitStr(' implements ');
        }

        /** @var DefStructInterface $interface */
        foreach ($node->getInterfaces() as $i => $interface) {
            $this->outputEmitter->emitStr($interface->getAbsoluteInterfaceName());
            if ($i < count($node->getInterfaces()) - 1) {
                $this->outputEmitter->emitStr(', ');
            }
        }

        $this->outputEmitter->emitLine(' {');

        $this->outputEmitter->increaseIndentLevel();
    }

    private function emitProperties(DefStructNode $node): void
    {
        foreach ($node->getParams() as $i => $param) {
            $this->outputEmitter->emitStr('protected ');
            $this->outputEmitter->emitPhpVariable($param);
            $this->outputEmitter->emitLine(';');
        }
    }

    private function emitAllowedKeys(DefStructNode $node): void
    {
        $paramCount = count($node->getParams());
        $this->outputEmitter->emitStr('protected const ALLOWED_KEYS = [', $node->getStartSourceLocation());

        foreach ($node->getParams() as $i => $param) {
            $this->outputEmitter->emitStr("'" . $param->getName() . "'");
            if ($i < $paramCount - 1) {
                $this->outputEmitter->emitStr(', ', $node->getStartSourceLocation());
            }
        }

        $this->outputEmitter->emitLine('];', $node->getStartSourceLocation());
    }

    private function emitConstructor(DefStructNode $node): void
    {
        $this->outputEmitter->emitStr('public function __construct(', $node->getStartSourceLocation());

        foreach ($node->getParams() as $i => $param) {
            $this->outputEmitter->emitPhpVariable($param);
            $this->outputEmitter->emitStr(', ', $node->getStartSourceLocation());
        }

        $this->outputEmitter->emitPhpVariable(Symbol::create('meta'));
        $this->outputEmitter->emitStr(' = null');

        $this->outputEmitter->emitLine(') {', $node->getStartSourceLocation());
        $this->outputEmitter->increaseIndentLevel();

        $this->outputEmitter->emitLine('parent::__construct();');

        foreach ($node->getParams() as $i => $param) {
            $keyword = Keyword::create($param->getName());
            $keyword->setStartLocation($node->getStartSourceLocation());

            $propertyName = $this->outputEmitter->mungeEncode($param->getName());

            $this->outputEmitter->emitStr('$this->' . $propertyName . ' = ', $node->getStartSourceLocation());
            $this->outputEmitter->emitPhpVariable($param);
            $this->outputEmitter->emitLine(';', $node->getStartSourceLocation());
        }

        $this->outputEmitter->emitStr('$this->meta = ', $node->getStartSourceLocation());
        $this->outputEmitter->emitPhpVariable(Symbol::create('meta'));
        $this->outputEmitter->emitLine(';', $node->getStartSourceLocation());

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

    private function emitInterfaceImplementation(DefStructInterface $interface): void
    {
        /** @var DefStructMethod $method */
        foreach ($interface->getMethods() as $method) {
            $this->outputEmitter->emitLine();
            $this->methodEmitter->emit($method->getName()->getName(), $method->getFnNode());
        }
    }

    private function emitClassEnd(DefStructNode $node): void
    {
        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitLine('}', $node->getStartSourceLocation());
    }
}
