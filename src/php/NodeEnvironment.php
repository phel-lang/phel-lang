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
     * @var RecurFrame[]
     */
    protected $recurFrames;

    public function __construct($locals, $context, $shadowed, $recurFrames)
    {
        $this->locals = $locals;
        $this->context = $context;
        $this->shadowed = $shadowed;
        $this->recurFrames = $recurFrames;
    }

    public static function empty() {
        return new NodeEnvironment([], NodeEnvironment::CTX_STMT, [], []);
    }

    public function getLocals() {
        return $this->locals;
    }

    /**
     * Checks if a local variable is shadowed
     * 
     * @param Symbol $local The local variable
     * 
     * @return boolean
     */
    public function isShadowed(Symbol $local) {
        return array_key_exists($local->getName(), $this->shadowed);
    }

    /**
     * Gets the shadowed name of a local variable
     * 
     * @param Symbol $local The local variable
     * 
     * @return Symbol|null
     */
    public function getShadowed(Symbol $local) {
        if ($this->isShadowed($local)) {
            return $this->shadowed[$local->getName()];
        } else {
            return null;
        }
    }

    public function getContext() {
        return $this->context;
    }

    public function withMergedLocals(array $locals): NodeEnvironment {
        $finalLocals = array_unique(array_merge($this->locals, $locals));
        return new NodeEnvironment($finalLocals, $this->context, $this->shadowed, $this->recurFrames);
    }

    public function withShadowedLocal(Symbol $local, Symbol $shadow) {
        $finalShadowed = array_merge($this->shadowed, [$local->getName() => $shadow]);
        return new NodeEnvironment($this->locals, $this->context, $finalShadowed, $this->recurFrames);
    }

    public function withLocals(array $locals): NodeEnvironment {
        return new NodeEnvironment($locals, $this->context, $this->shadowed, $this->recurFrames);
    }

    public function withContext($context): NodeEnvironment {
        return new NodeEnvironment($this->locals, $context, $this->shadowed, $this->recurFrames);
    }

    public function withAddedRecurFrame(RecurFrame $frame) {
        return new NodeEnvironment($this->locals, $this->context, $this->shadowed, array_merge($this->recurFrames, [$frame]));
    }

    public function withDisallowRecurFrame() {
        return new NodeEnvironment($this->locals, $this->context, $this->shadowed, array_merge($this->recurFrames, [null]));
    }

    public function getCurrentRecurFrame() {
        if (count($this->recurFrames) > 0) {
            return $this->recurFrames[count($this->recurFrames) - 1];
        } else {
            return null;
        }        
    }
}