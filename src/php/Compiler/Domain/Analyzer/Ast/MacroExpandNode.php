<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\Ast;

use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;
use Phel\Lang\TypeFactory;

final class MacroExpandNode extends AbstractNode
{
    public function __construct(
        private NodeEnvironmentInterface $env,
        private readonly AbstractNode $value,
        ?SourceLocation $sourceLocation = null,
    ) {
        parent::__construct($env, $sourceLocation);
    }

    public function getValue(): AbstractNode
    {
        return $this->value; // as dummy.

        // Note:
        // `$this->value` is expanded Node.
        // If returned as is, the Node is passed to Emitter and evaluated when PHP is executed.
        // I thought that if we could convert the Node to a `quote`ed Node that is not evaluated in this method, we could reproduce the state in which the macro is expanded.

        // return $this->quoteNode($this->value);
    }

    /** Implementation is not complete. */
    private function quoteNode(AbstractNode $node): AbstractNode
    {
        switch (true) {
            case $node instanceof DoNode:
                return array_map(fn($x) => $this->quoteNode($x), $node->getStmts());
            case $node instanceof BindingNode:
                return new VectorNode($this->env, [
                    Symbol::create($node->getSymbol()->getName()),
                    $this->quoteNode($node->getInitExpr()),
                ]);
            case $node instanceof LetNode:
                return TypeFactory::getInstance()->persistentListFromArray([
                    Symbol::create(Symbol::NAME_LET),
                    ...array_map(fn($x) => $this->quoteNode($x), $node->getBindings()),
                    $this->quoteNode($node->getBodyExpr()),                    
                ]);
            default:
                return $node;
        }
    }
}
