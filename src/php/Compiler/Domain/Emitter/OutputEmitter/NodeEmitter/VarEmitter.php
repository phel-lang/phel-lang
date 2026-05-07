<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\VarNode;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitterInterface;
use Phel\Compiler\Domain\Emitter\OutputEmitter\PhpStringEscape;
use Phel\Lang\Registry;

use function assert;

final class VarEmitter implements NodeEmitterInterface
{
    use WithOutputEmitterTrait;

    public function emit(AbstractNode $node): void
    {
        assert($node instanceof VarNode);

        $this->outputEmitter->emitContextPrefix($node->getEnv(), $node->getStartSourceLocation());
        $this->outputEmitter->emitStr('\\' . Registry::class . '::getInstance()->getVar("');
        $this->outputEmitter->emitStr(PhpStringEscape::doubleQuoted($this->outputEmitter->mungeEncodeRegistryKey($node->getNamespace())));
        $this->outputEmitter->emitStr('", "');
        $this->outputEmitter->emitStr(PhpStringEscape::doubleQuoted($node->getName()));
        $this->outputEmitter->emitStr('")');
        $this->outputEmitter->emitContextSuffix($node->getEnv(), $node->getStartSourceLocation());
    }
}
