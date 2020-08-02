<?php

declare(strict_types=1);

namespace Phel\Emitter\NodeEmitter;

use Phel\Ast\Node;
use Phel\Ast\NsNode;
use Phel\Emitter\NodeEmitter;
use Phel\Lang\Symbol;
use function addslashes;

final class NsEmitter implements NodeEmitter
{
    use WithOutputEmitter;

    public function emit(Node $node): void
    {
        assert($node instanceof NsNode);

        foreach ($node->getRequireNs() as $i => $ns) {
            $this->outputEmitter->emitLine(
                '\Phel\Runtime::getInstance()->loadNs("' . addslashes($ns->getName()) . '");',
                $ns->getStartLocation()
            );
        }

        $this->outputEmitter->emitLine(
            '\Phel\Runtime::getInstance()->getEnv()->setNs("' . addslashes($node->getNamespace()) . '");',
            $node->getStartSourceLocation()
        );

        $nsSym = Symbol::create('*ns*');
        $nsSym->setStartLocation($node->getStartSourceLocation());
        $this->outputEmitter->emitGlobalBase('phel\\core', $nsSym);
        $this->outputEmitter->emitStr(' = ', $node->getStartSourceLocation());
        $this->outputEmitter->emitLiteral('\\' . $this->outputEmitter->mungeEncodeNs($node->getNamespace()));
        $this->outputEmitter->emitLine(';', $node->getStartSourceLocation());
    }
}
