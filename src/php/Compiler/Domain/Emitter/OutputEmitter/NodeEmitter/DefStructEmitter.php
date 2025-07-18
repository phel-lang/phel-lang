<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\DefStructNode;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitterInterface;
use Phel\Compiler\Domain\Emitter\OutputEmitterInterface;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;

use function assert;
use function count;

final readonly class DefStructEmitter implements NodeEmitterInterface
{
    public function __construct(
        private OutputEmitterInterface $outputEmitter,
        private MethodEmitter $methodEmitter,
    ) {
    }

    public function emit(AbstractNode $node): void
    {
        assert($node instanceof DefStructNode);

        $this->emitClassBegin($node);
        $this->emitAllowedKeys($node);
        $this->emitProperties($node);
        $this->emitConstructor($node);
        $this->emitInterfaces($node);
        $this->emitClassEnd($node);
    }

    private function emitClassBegin(DefStructNode $node): void
    {
        if ($this->outputEmitter->getOptions()->isStatementEmitMode()) {
            $this->outputEmitter->emitLine(
                'namespace ' . $this->outputEmitter->mungeEncodeNs($node->getNamespace()) . ';',
                $node->getStartSourceLocation(),
            );
        }

        $fqcn = $this->outputEmitter->mungeEncodeNs($node->getNamespace())
            . '\\' . $this->outputEmitter->mungeEncode($node->getName()->getName());
        $this->outputEmitter->emitLine("if (!class_exists('" . $fqcn . "')) {", $node->getStartSourceLocation());

        $this->outputEmitter->emitStr(
            'class ' . $this->outputEmitter->mungeEncode($node->getName()->getName()) . ' extends \Phel\Lang\Collections\Struct\AbstractPersistentStruct',
            $node->getStartSourceLocation(),
        );

        if ($node->getInterfaces() !== []) {
            $this->outputEmitter->emitStr(' implements ');
        }

        $interfaces = $node->getInterfaces();
        $interfacesCount = count($interfaces);
        foreach ($interfaces as $i => $defStruct) {
            $this->outputEmitter->emitStr($defStruct->getAbsoluteInterfaceName());
            if ($i < $interfacesCount - 1) {
                $this->outputEmitter->emitStr(', ');
            }
        }

        $this->outputEmitter->emitLine(' {');

        $this->outputEmitter->increaseIndentLevel();
        $this->outputEmitter->emitLine();
    }

    private function emitAllowedKeys(DefStructNode $node): void
    {
        $params = $node->getParams();
        $paramCount = count($params);
        $this->outputEmitter->emitStr('protected const array ALLOWED_KEYS = [', $node->getStartSourceLocation());

        foreach ($params as $i => $param) {
            $this->outputEmitter->emitStr("'" . $param->getName() . "'");
            if ($i < $paramCount - 1) {
                $this->outputEmitter->emitStr(', ', $node->getStartSourceLocation());
            }
        }

        $this->outputEmitter->emitLine('];', $node->getStartSourceLocation());
        $this->outputEmitter->emitLine();
    }

    private function emitProperties(DefStructNode $node): void
    {
        $params = $node->getParams();
        foreach ($params as $param) {
            $this->outputEmitter->emitStr('protected ');
            $this->outputEmitter->emitPhpVariable($param);
            $this->outputEmitter->emitLine(';');
        }

        $this->outputEmitter->emitLine();
    }

    private function emitConstructor(DefStructNode $node): void
    {
        $this->outputEmitter->emitStr('public function __construct(', $node->getStartSourceLocation());

        $params = $node->getParams();
        foreach ($params as $param) {
            $this->outputEmitter->emitPhpVariable($param);
            $this->outputEmitter->emitStr(', ', $node->getStartSourceLocation());
        }

        $this->outputEmitter->emitPhpVariable(Symbol::create('meta'));
        $this->outputEmitter->emitStr(' = null');

        $this->outputEmitter->emitLine(') {', $node->getStartSourceLocation());
        $this->outputEmitter->increaseIndentLevel();

        $this->outputEmitter->emitLine('parent::__construct();');

        foreach ($params as $param) {
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

    private function emitInterfaces(DefStructNode $node): void
    {
        foreach ($node->getInterfaces() as $defStruct) {
            foreach ($defStruct->getMethods() as $method) {
                $this->outputEmitter->emitLine();
                $this->methodEmitter->emit($method->getName()->getName(), $method->getFnNode());
            }
        }
    }

    private function emitClassEnd(DefStructNode $node): void
    {
        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitLine('}', $node->getStartSourceLocation());
        $this->outputEmitter->emitLine('}', $node->getStartSourceLocation());
    }
}
