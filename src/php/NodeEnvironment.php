<?php

namespace Phel;

use Phel\Lang\Symbol;
use Phel\Lang\Table;

class NodeEnvironment {

    const CTX_EXPR = 'expr';
    const CTX_STMT = 'stmt';
    const CTX_RET = 'ret';

    /**
     * @var Symbol[]
     */
    protected $locals;

    /**
     * A mapping of local variables to shadowed names
     * 
     * @var array
     */
    protected $shadowed;

    /**
     * @var string
     */
    protected $context;

    /**
     * @var array
     */
    protected $recurFrames;

    /**
     * @var string
     */
    protected $boundTo = '';

    /**
     * Constructor
     * 
     * @param Symbol[] $locals A list of local symbols
     * @param string $context The current context (Expression, Statement or Return)
     * @param Symbol[] $shadowed A list of shadowed variables
     * @param array $recurFrames A list of RecurFrame
     * @param string|null $boundTo A variable this is bound to
     */
    public function __construct($locals, $context, $shadowed, $recurFrames, $boundTo = null)
    {
        $this->locals = $locals;
        $this->context = $context;
        $this->shadowed = $shadowed;
        $this->recurFrames = $recurFrames;
        if ($boundTo) {
            $this->boundTo = $boundTo;
        }
    }

    public static function empty(): NodeEnvironment {
        return new NodeEnvironment([], NodeEnvironment::CTX_STMT, [], []);
    }

    /**
     * @return Symbol[]
     */
    public function getLocals() {
        return $this->locals;
    }

    public function hasLocal(Symbol $x): bool {
        return in_array(new Symbol($x->getName()), $this->locals);
    }

    /**
     * Checks if a local variable is shadowed
     * 
     * @param Symbol $local The local variable
     * 
     * @return bool
     */
    public function isShadowed(Symbol $local): bool {
        return array_key_exists($local->getName(), $this->shadowed);
    }

    /**
     * Gets the shadowed name of a local variable
     * 
     * @param Symbol $local The local variable
     * 
     * @return Symbol|null
     */
    public function getShadowed(Symbol $local): ?Symbol {
        if ($this->isShadowed($local)) {
            return $this->shadowed[$local->getName()];
        } else {
            return null;
        }
    }

    public function getContext(): string {
        return $this->context;
    }

    public function withMergedLocals(array $locals): NodeEnvironment {
        $finalLocals = array_unique(array_merge($this->locals, array_map(fn($s) => new Symbol($s->getName()), $locals)));
        return new NodeEnvironment($finalLocals, $this->context, $this->shadowed, $this->recurFrames, $this->boundTo);
    }

    public function withShadowedLocal(Symbol $local, Symbol $shadow): NodeEnvironment {
        $finalShadowed = array_merge($this->shadowed, [$local->getName() => $shadow]);
        return new NodeEnvironment($this->locals, $this->context, $finalShadowed, $this->recurFrames, $this->boundTo);
    }

    public function withLocals(array $locals): NodeEnvironment {
        return new NodeEnvironment($locals, $this->context, $this->shadowed, $this->recurFrames, $this->boundTo);
    }

    public function withContext(string $context): NodeEnvironment {
        return new NodeEnvironment($this->locals, $context, $this->shadowed, $this->recurFrames, $this->boundTo);
    }

    public function withAddedRecurFrame(RecurFrame $frame): NodeEnvironment {
        return new NodeEnvironment($this->locals, $this->context, $this->shadowed, array_merge($this->recurFrames, [$frame]), $this->boundTo);
    }

    public function withDisallowRecurFrame(): NodeEnvironment {
        return new NodeEnvironment($this->locals, $this->context, $this->shadowed, array_merge($this->recurFrames, [null]), $this->boundTo);
    }

    public function getCurrentRecurFrame(): ?RecurFrame {
        if (count($this->recurFrames) > 0) {
            return $this->recurFrames[count($this->recurFrames) - 1];
        } else {
            return null;
        }        
    }

    public function withBoundTo(string $boundTo): NodeEnvironment {
        return new NodeEnvironment($this->locals, $this->context, $this->shadowed, $this->recurFrames, $boundTo);
    }

    public function getBoundTo() {
        return $this->boundTo;
    }
}
