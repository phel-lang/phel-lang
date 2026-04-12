<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\DefExceptionNode;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitterInterface;
use Phel\Compiler\Domain\Emitter\OutputEmitterInterface;

use function assert;

final readonly class DefExceptionEmitter implements NodeEmitterInterface
{
    public function __construct(private OutputEmitterInterface $outputEmitter) {}

    public function emit(AbstractNode $node): void
    {
        assert($node instanceof DefExceptionNode);

        if ($this->outputEmitter->getOptions()->isStatementEmitMode()) {
            $this->emitViaEval($node);
        } else {
            $this->emitInline($node);
        }
    }

    /**
     * In statement mode, the class definition may end up inside a function
     * wrapper. PHP forbids namespace declarations inside functions, so we
     * capture the class body at compile time and emit it as an eval() call.
     */
    private function emitViaEval(DefExceptionNode $node): void
    {
        $ns = $this->outputEmitter->mungeEncodeNs($node->getNamespace());
        $fqcn = $ns . '\\' . $this->outputEmitter->mungeEncode($node->getName()->getName());

        ob_start();
        $this->emitClassBody($node);
        $classBody = (string) ob_get_clean();

        $this->outputEmitter->emitLine("if (!class_exists('" . $fqcn . "')) {", $node->getStartSourceLocation());
        $this->outputEmitter->increaseIndentLevel();

        $evalCode = 'namespace ' . $ns . ";\n" . $classBody;
        $this->outputEmitter->emitLine(
            'eval(' . var_export($evalCode, true) . ');',
            $node->getStartSourceLocation(),
        );

        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitLine('}', $node->getStartSourceLocation());
    }

    /**
     * In file/cache mode the NsEmitter already declared the namespace.
     */
    private function emitInline(DefExceptionNode $node): void
    {
        $fqcn = $this->outputEmitter->mungeEncodeNs($node->getNamespace())
            . '\\' . $this->outputEmitter->mungeEncode($node->getName()->getName());
        $this->outputEmitter->emitLine("if (!class_exists('" . $fqcn . "')) {", $node->getStartSourceLocation());

        $this->emitClassBody($node);

        $this->outputEmitter->emitLine('}', $node->getStartSourceLocation());
    }

    private function emitClassBody(DefExceptionNode $node): void
    {
        $this->outputEmitter->emitStr(
            'class ' . $this->outputEmitter->mungeEncode($node->getName()->getName()) . ' extends ',
            $node->getStartSourceLocation(),
        );
        $this->outputEmitter->emitNode($node->getParent());
        $this->outputEmitter->emitLine(' {');
        $this->outputEmitter->increaseIndentLevel();

        $this->outputEmitter->emitLine('public function __construct($message = "", $code = 0, ?\Throwable $previous = null) {');
        $this->outputEmitter->increaseIndentLevel();
        $this->outputEmitter->emitLine('parent::__construct($message, $code, $previous);');
        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitLine('}');

        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitLine('}', $node->getStartSourceLocation());
    }
}
