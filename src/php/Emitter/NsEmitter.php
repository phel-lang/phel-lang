<?php

declare(strict_types=1);

namespace Phel\Emitter;

use Phel\Ast\Node;
use Phel\Ast\NsNode;
use Phel\Emitter;
use Phel\Lang\Symbol;
use Phel\Munge;

final class NsEmitter implements NodeEmitter
{
    private Emitter $emitter;

    public function __construct(Emitter $emitter)
    {
        $this->emitter = $emitter;
    }

    public function emit(Node $node): void
    {
        assert($node instanceof NsNode);

        foreach ($node->getRequireNs() as $i => $ns) {
            $this->emitter->emitLine('\Phel\Runtime::getInstance()->loadNs("' . \addslashes($ns->getName()) . '");', $ns->getStartLocation());
        }

        $this->emitter->emitLine('\Phel\Runtime::getInstance()->getEnv()->setNs("' . \addslashes($node->getNamespace()) . '");', $node->getStartSourceLocation());

        $nsSym = Symbol::create('*ns*');
        $nsSym->setStartLocation($node->getStartSourceLocation());
        $this->emitter->emitGlobalBase('phel\\core', $nsSym);
        $this->emitter->emitStr(' = ', $node->getStartSourceLocation());
        $this->emitter->emitPhel('\\' . Munge::encodeNs($node->getNamespace()));
        $this->emitter->emitLine(';', $node->getStartSourceLocation());
    }
}
