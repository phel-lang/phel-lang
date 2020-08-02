<?php

declare(strict_types=1);

namespace Phel\Emitter\NodeEmitter;

use Phel\Ast\Node;
use Phel\Ast\NsNode;
use Phel\Emitter\NodeEmitter;
use Phel\Lang\Symbol;
use Phel\Munge;
use function addslashes;

final class NsEmitter implements NodeEmitter
{
    use WithEmitter;

    public function emit(Node $node): void
    {
        assert($node instanceof NsNode);

        foreach ($node->getRequireNs() as $i => $ns) {
            $this->emitter->emitLine(
                '\Phel\Runtime::getInstance()->loadNs("' . addslashes($ns->getName()) . '");',
                $ns->getStartLocation()
            );
        }

        $this->emitter->emitLine(
            '\Phel\Runtime::getInstance()->getEnv()->setNs("' . addslashes($node->getNamespace()) . '");',
            $node->getStartSourceLocation()
        );

        $nsSym = Symbol::create('*ns*');
        $nsSym->setStartLocation($node->getStartSourceLocation());
        $this->emitter->emitGlobalBase('phel\\core', $nsSym);
        $this->emitter->emitStr(' = ', $node->getStartSourceLocation());
        $this->emitter->emitLiteral('\\' . Munge::encodeNs($node->getNamespace()));
        $this->emitter->emitLine(';', $node->getStartSourceLocation());
    }
}
