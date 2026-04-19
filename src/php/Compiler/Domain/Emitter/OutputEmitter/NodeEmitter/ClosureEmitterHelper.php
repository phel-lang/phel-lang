<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Domain\Emitter\OutputEmitterInterface;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;

use function count;

/**
 * Shared helpers for emitting PHP anonymous classes that capture local variables.
 *
 * Used by FnAsClassEmitter, ReifyEmitter, MultiFnAsClassEmitter, and MethodEmitter
 * to centralize the use-normalization, property, and constructor-argument patterns.
 */
final readonly class ClosureEmitterHelper
{
    public function __construct(
        private OutputEmitterInterface $outputEmitter,
    ) {}

    public function normalizeUse(Symbol $use, NodeEnvironmentInterface $env): Symbol
    {
        $shadowed = $env->getShadowed($use);

        return $shadowed instanceof Symbol ? $shadowed : $use;
    }

    /**
     * Emits the captured variables as constructor arguments: `$var1, $var2`.
     *
     * @param list<Symbol> $uses
     */
    public function emitConstructorArguments(array $uses, NodeEnvironmentInterface $env, ?SourceLocation $loc): void
    {
        $count = count($uses);
        foreach ($uses as $i => $use) {
            $this->outputEmitter->emitPhpVariable(
                $this->normalizeUse($use, $env),
                $use->getStartLocation(),
            );

            if ($i < $count - 1) {
                $this->outputEmitter->emitStr(', ', $loc);
            }
        }
    }

    /**
     * Emits `private $varName;` for each captured variable.
     *
     * @param list<Symbol> $uses
     */
    public function emitProperties(array $uses, NodeEnvironmentInterface $env, ?SourceLocation $loc): void
    {
        foreach ($uses as $use) {
            $normalizedUse = $this->normalizeUse($use, $env);
            $this->outputEmitter->emitLine(
                'private $' . $this->outputEmitter->mungeEncode($normalizedUse->getName()) . ';',
                $loc,
            );
        }
    }

    /**
     * Emits the full constructor: parameter list, property assignments.
     * Emits nothing if there are no captured variables.
     *
     * @param list<Symbol> $uses
     */
    public function emitConstructor(array $uses, NodeEnvironmentInterface $env, ?SourceLocation $loc): void
    {
        if ($uses === []) {
            return;
        }

        $this->outputEmitter->emitLine();
        $this->outputEmitter->emitStr('public function __construct(', $loc);

        $count = count($uses);
        foreach ($uses as $i => $use) {
            $this->outputEmitter->emitPhpVariable($this->normalizeUse($use, $env), $loc);

            if ($i < $count - 1) {
                $this->outputEmitter->emitStr(', ', $loc);
            }
        }

        $this->outputEmitter->emitLine(') {', $loc);
        $this->outputEmitter->increaseIndentLevel();

        foreach ($uses as $use) {
            $normalizedUse = $this->normalizeUse($use, $env);
            $varName = $this->outputEmitter->mungeEncode($normalizedUse->getName());
            $this->outputEmitter->emitLine('$this->' . $varName . ' = $' . $varName . ';', $loc);
        }

        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitLine('}', $loc);
    }
}
