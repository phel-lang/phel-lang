<?php

declare(strict_types=1);

namespace Phel\Compiler\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Analyzer\Ast\NsNode;
use Phel\Compiler\Emitter\OutputEmitter\NodeEmitterInterface;
use Phel\Lang\Symbol;
use function addslashes;

final class NsEmitter implements NodeEmitterInterface
{
    use WithOutputEmitterTrait;

    public function emit(AbstractNode $node): void
    {
        assert($node instanceof NsNode);

        $this->emitNamespace($node);
        $this->emitRequireFiles($node);
        $this->emitRequiredNamespaces($node);
        $this->emitCurrentNamespace($node);
    }

    private function emitNamespace(NsNode $node): void
    {
        if ($this->outputEmitter->getOptions()->isFileEmitMode()) {
            $this->outputEmitter->emitStr('namespace ', $node->getStartSourceLocation());
            $this->outputEmitter->emitStr($this->outputEmitter->mungeEncodeNs($node->getNamespace()), $node->getStartSourceLocation());
            $this->outputEmitter->emitLine(';', $node->getStartSourceLocation());
        }
    }

    private function emitRequireFiles(NsNode $node): void
    {
        if ($this->outputEmitter->getOptions()->isFileEmitMode()) {
            foreach ($node->getRequireFiles() as $path) {
                $this->outputEmitter->emitStr('require_once ', $node->getStartSourceLocation());
                $this->outputEmitter->emitLiteral($path);
                $this->outputEmitter->emitLine(';', $node->getStartSourceLocation());
            }
        }
    }

    private function emitRequiredNamespaces(NsNode $node): void
    {
        if ($this->outputEmitter->getOptions()->isFileEmitMode()) {
            foreach ($node->getRequireNs() as $i => $ns) {
                $depth = count(explode('\\', $node->getNamespace())) - 1;
                $filename = str_replace('\\', '/', $this->outputEmitter->mungeEncodeNs($ns->getName()));
                $relativePath = str_repeat('/..', $depth) . '/' . $filename . '.php';
                $absolutePath = '__DIR__ . \'' . $relativePath . '\'';

                $this->outputEmitter->emitLine(
                    'require_once ' . $absolutePath . ';',
                    $ns->getStartLocation()
                );
            }
        }
    }

    private function emitCurrentNamespace(NsNode $node): void
    {
        if (!$this->outputEmitter->getOptions()->isFileEmitMode()) {
            $this->outputEmitter->emitLine(
                '\Phel\Compiler\Analyzer\Environment\GlobalEnvironmentSingleton::getInstance()->setNs("' . addslashes($node->getNamespace()) . '");',
                $node->getStartSourceLocation()
            );
        }

        $nsSym = Symbol::create('*ns*');
        $nsSym->setStartLocation($node->getStartSourceLocation());
        $this->outputEmitter->emitGlobalBase('phel\\core', $nsSym);
        $this->outputEmitter->emitStr(' = ', $node->getStartSourceLocation());
        $this->outputEmitter->emitLiteral($this->outputEmitter->mungeEncodeNs($node->getNamespace()));
        $this->outputEmitter->emitLine(';', $node->getStartSourceLocation());
    }
}
