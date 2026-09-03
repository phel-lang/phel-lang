<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter;

use Closure;
use Phel\Compiler\Domain\Emitter\OutputEmitterInterface;
use Phel\Lang\SourceLocation;

use function ob_get_clean;
use function ob_start;
use function var_export;

/**
 * Shared decision for emitters that generate a top-level PHP type declaration
 * (`defstruct`, `defexception`, `defenum`, `definterface`). The declaration
 * must be lifted into an `eval()` when it would otherwise land somewhere PHP
 * forbids: inside a function wrapper in statement mode (namespace not
 * allowed), or inside another class's method body, e.g. used inside a
 * `deftest` body.
 *
 * Using emitters must expose the `$outputEmitter` property (as every node
 * emitter already does).
 *
 * @property-read OutputEmitterInterface $outputEmitter
 *
 * @internal
 */
trait EvalGuardedEmitterTrait
{
    private function shouldEmitViaEval(): bool
    {
        if ($this->outputEmitter->getOptions()->isStatementEmitMode()) {
            return true;
        }

        return $this->outputEmitter->isInsideClassScope();
    }

    /**
     * Emits `$emitBody` inside an `if (!<exists>('<fqcn>'))` guard: inline when
     * the `NsEmitter` already declared the namespace, otherwise captured and
     * handed to `eval()` together with its own `namespace` statement.
     *
     * @param Closure():void $emitBody
     */
    private function emitGuardedTypeDeclaration(
        string $namespace,
        string $name,
        string $existsFunction,
        ?SourceLocation $sourceLocation,
        Closure $emitBody,
    ): void {
        $ns = $this->outputEmitter->mungeEncodePhpNs($namespace);
        $fqcn = $ns . '\\' . $this->outputEmitter->mungeEncode($name);
        $guard = 'if (!' . $existsFunction . "('" . $fqcn . "')) {";

        if (!$this->shouldEmitViaEval()) {
            $this->outputEmitter->emitLine($guard, $sourceLocation);
            $emitBody();
            $this->outputEmitter->emitLine('}', $sourceLocation);

            return;
        }

        ob_start();
        $emitBody();
        $body = (string) ob_get_clean();

        $this->outputEmitter->emitLine($guard, $sourceLocation);
        $this->outputEmitter->increaseIndentLevel();
        $this->outputEmitter->emitLine(
            'eval(' . var_export('namespace ' . $ns . ";\n" . $body, true) . ');',
            $sourceLocation,
        );
        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitLine('}', $sourceLocation);
    }
}
