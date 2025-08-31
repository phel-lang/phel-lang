<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\FnNode;
use Phel\Compiler\Domain\Analyzer\Ast\MultiFnNode;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitterInterface;
use Phel\Compiler\Domain\Emitter\OutputEmitterInterface;
use Phel\Lang\Symbol;

use function assert;
use function count;

final readonly class MultiFnAsClassEmitter implements NodeEmitterInterface
{
    public function __construct(
        private OutputEmitterInterface $outputEmitter,
    ) {
    }

    public function emit(AbstractNode $node): void
    {
        assert($node instanceof MultiFnNode);

        $fnNodes = $node->getFnNodes();
        $uses = $this->collectUses($fnNodes);

        $this->emitClassBegin($node, $uses);
        $this->emitProperties($node, $uses, count($fnNodes));
        $this->emitConstructor($node, $uses, $fnNodes);
        $this->emitInvoke($node, $fnNodes);
        $this->emitClassEnd($node);
    }

    /**
     * @param list<FnNode> $fnNodes
     *
     * @return list<Symbol>
     */
    private function collectUses(array $fnNodes): array
    {
        $byName = [];   // name => first Use instance seen

        foreach ($fnNodes as $fnNode) {
            foreach ($fnNode->getUses() as $use) {
                $name = $use->getName();
                if (!isset($byName[$name])) {
                    $byName[$name] = $use;
                }
            }
        }

        return array_values($byName);
    }

    /**
     * @param list<Symbol> $uses
     */
    private function emitClassBegin(MultiFnNode $node, array $uses): void
    {
        $this->outputEmitter->emitContextPrefix($node->getEnv(), $node->getStartSourceLocation());
        $this->outputEmitter->emitStr('new class(', $node->getStartSourceLocation());

        $usesCount = count($uses);
        foreach ($uses as $i => $use) {
            $loc = $use->getStartLocation();
            /** @var Symbol $normalizedUse */
            $normalizedUse = $node->getEnv()->getShadowed($use) instanceof Symbol
                ? $node->getEnv()->getShadowed($use)
                : $use;
            $this->outputEmitter->emitPhpVariable($normalizedUse, $loc);
            if ($i < $usesCount - 1) {
                $this->outputEmitter->emitStr(', ', $node->getStartSourceLocation());
            }
        }

        $this->outputEmitter->emitLine(') extends \\Phel\\Lang\\AbstractFn {', $node->getStartSourceLocation());
        $this->outputEmitter->increaseIndentLevel();
    }

    /**
     * @param list<Symbol> $uses
     */
    private function emitProperties(MultiFnNode $node, array $uses, int $fnCount): void
    {
        $ns = addslashes($this->outputEmitter->mungeEncodeNs($node->getEnv()->getBoundTo()));
        $this->outputEmitter->emitLine('public const BOUND_TO = "' . $ns . '";', $node->getStartSourceLocation());

        foreach ($uses as $use) {
            /** @var Symbol $normalizedUse */
            $normalizedUse = $node->getEnv()->getShadowed($use) instanceof Symbol
                ? $node->getEnv()->getShadowed($use)
                : $use;
            $this->outputEmitter->emitLine(
                'private $' . $this->outputEmitter->mungeEncode($normalizedUse->getName()) . ';',
                $node->getStartSourceLocation(),
            );
        }

        for ($i = 0; $i < $fnCount; ++$i) {
            $this->outputEmitter->emitLine('private $fn' . $i . ';', $node->getStartSourceLocation());
        }
    }

    /**
     * @param list<Symbol> $uses
     * @param list<FnNode> $fnNodes
     */
    private function emitConstructor(MultiFnNode $node, array $uses, array $fnNodes): void
    {
        $usesCount = count($uses);
        $this->outputEmitter->emitLine();
        $this->outputEmitter->emitStr('public function __construct(', $node->getStartSourceLocation());
        foreach ($uses as $i => $use) {
            /** @var Symbol $normalizedUse */
            $normalizedUse = $node->getEnv()->getShadowed($use) instanceof Symbol
                ? $node->getEnv()->getShadowed($use)
                : $use;
            $this->outputEmitter->emitPhpVariable($normalizedUse, $node->getStartSourceLocation());
            if ($i < $usesCount - 1) {
                $this->outputEmitter->emitStr(', ', $node->getStartSourceLocation());
            }
        }

        $this->outputEmitter->emitLine(') {', $node->getStartSourceLocation());
        $this->outputEmitter->increaseIndentLevel();

        foreach ($uses as $use) {
            /** @var Symbol $normalizedUse */
            $normalizedUse = $node->getEnv()->getShadowed($use) instanceof Symbol
                ? $node->getEnv()->getShadowed($use)
                : $use;
            $varName = $this->outputEmitter->mungeEncode($normalizedUse->getName());
            $this->outputEmitter->emitLine(
                '$this->' . $varName . ' = $' . $varName . ';',
                $node->getStartSourceLocation(),
            );
        }

        foreach ($fnNodes as $i => $fnNode) {
            $this->outputEmitter->emitStr('$this->fn' . $i . ' = ', $node->getStartSourceLocation());
            $this->outputEmitter->emitNode($fnNode);
            $this->outputEmitter->emitLine(';', $node->getStartSourceLocation());
        }

        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitLine('}', $node->getStartSourceLocation());
        $this->outputEmitter->emitLine();
    }

    /**
     * @param list<FnNode> $fnNodes
     */
    private function emitInvoke(MultiFnNode $node, array $fnNodes): void
    {
        $this->outputEmitter->emitLine('public function __invoke(...$args) {', $node->getStartSourceLocation());
        $this->outputEmitter->increaseIndentLevel();
        $this->outputEmitter->emitLine('$argc = \count($args);', $node->getStartSourceLocation());
        $this->outputEmitter->emitLine('switch ($argc) {', $node->getStartSourceLocation());
        $this->outputEmitter->increaseIndentLevel();

        $variadicIndex = null;
        foreach ($fnNodes as $i => $fnNode) {
            if ($fnNode->isVariadic()) {
                $variadicIndex = $i;
                continue;
            }

            $arity = count($fnNode->getParams());
            $this->outputEmitter->emitLine('case ' . $arity . ':', $node->getStartSourceLocation());
            $this->outputEmitter->increaseIndentLevel();
            $params = [];
            for ($p = 0; $p < $arity; ++$p) {
                $params[] = '$args[' . $p . ']';
            }

            $this->outputEmitter->emitLine('return ($this->fn' . $i . ')(' . implode(', ', $params) . ');', $node->getStartSourceLocation());
            $this->outputEmitter->decreaseIndentLevel();
        }

        $this->outputEmitter->emitLine('}', $node->getStartSourceLocation());

        if ($variadicIndex !== null) {
            $min = $fnNodes[$variadicIndex]->getMinArity();
            $this->outputEmitter->emitLine('if ($argc >= ' . $min . ') {', $node->getStartSourceLocation());
            $this->outputEmitter->increaseIndentLevel();
            $this->outputEmitter->emitLine('return ($this->fn' . $variadicIndex . ')(...$args);', $node->getStartSourceLocation());
            $this->outputEmitter->decreaseIndentLevel();
            $this->outputEmitter->emitLine('}', $node->getStartSourceLocation());
        }

        $this->outputEmitter->emitLine('throw new \\InvalidArgumentException("No matching function arity");', $node->getStartSourceLocation());
        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitLine('}', $node->getStartSourceLocation());
    }

    private function emitClassEnd(MultiFnNode $node): void
    {
        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitStr('}', $node->getStartSourceLocation());
        $this->outputEmitter->emitContextSuffix($node->getEnv(), $node->getStartSourceLocation());
    }
}
