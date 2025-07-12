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
    public function __construct(private OutputEmitterInterface $outputEmitter)
    {
    }

    public function emit(AbstractNode $node): void
    {
        assert($node instanceof DefExceptionNode);

        if ($this->outputEmitter->getOptions()->isStatementEmitMode()) {
            $this->outputEmitter->emitLine(
                'namespace ' . $this->outputEmitter->mungeEncodeNs($node->getNamespace()) . ';',
                $node->getStartSourceLocation(),
            );
        }

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
