<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter;

use Phel;
use Phel\Shared\Munge;

use function addslashes;

/**
 * Emits the runtime `*file*` / `*ns*` definition updates that both `ns` and
 * `in-ns` forms bake into their compiled output, so subsequent loads resolve
 * relative paths and the current namespace correctly. Composing emitters must
 * also use {@see WithOutputEmitterTrait}.
 */
trait NsStateDefinitionsEmitterTrait
{
    private function emitFileAndNsDefinitions(string $namespace, string $file): void
    {
        $this->emitCoreDefinition('*file*', $file);
        $this->emitCoreDefinition('*ns*', Munge::displayNs($namespace));
    }

    private function emitCoreDefinition(string $name, string $value): void
    {
        $this->outputEmitter->emitLine('\\' . Phel::class . '::addDefinition(');
        $this->outputEmitter->increaseIndentLevel();
        $this->outputEmitter->emitStr('"');
        $this->outputEmitter->emitStr(addslashes($this->outputEmitter->mungeEncodeRegistryKey('phel.core')));
        $this->outputEmitter->emitLine('",');
        $this->outputEmitter->emitStr('"');
        $this->outputEmitter->emitStr(addslashes($name));
        $this->outputEmitter->emitLine('",');
        $this->outputEmitter->emitLiteral($value);
        $this->outputEmitter->emitLine();
        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitLine(');');
    }
}
