<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Domain\Emitter\OutputEmitter\LiteralEmitter;
use Phel\Compiler\Domain\Emitter\OutputEmitter\MungeInterface;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitterFactory;
use Phel\Compiler\Domain\Emitter\OutputEmitter\OutputEmitterOptions;
use Phel\Compiler\Domain\Emitter\OutputEmitter\SourceMap\SourceMapState;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;
use Phel\Lang\TypeInterface;
use Phel\Printer\PrinterInterface;

use function count;
use function is_null;
use function strlen;

final class OutputEmitter implements OutputEmitterInterface
{
    private int $indentLevel = 0;

    public function __construct(
        private readonly bool $enableSourceMaps,
        private readonly NodeEmitterFactory $nodeEmitterFactory,
        private readonly MungeInterface $munge,
        private readonly PrinterInterface $printer,
        private readonly SourceMapState $sourceMapState,
        private readonly OutputEmitterOptions $options,
    ) {
    }

    public function getOptions(): OutputEmitterOptions
    {
        return $this->options;
    }

    public function resetIndentLevel(): void
    {
        $this->indentLevel = 0;
    }

    public function resetSourceMapState(): void
    {
        $this->sourceMapState->reset();
    }

    public function getSourceMapState(): SourceMapState
    {
        return $this->sourceMapState;
    }

    public function emitNode(AbstractNode $node): void
    {
        $nodeEmitter = $this->nodeEmitterFactory->createNodeEmitter($this, $node::class);
        $nodeEmitter->emit($node);
    }

    public function emitLine(string $str = '', ?SourceLocation $sl = null): void
    {
        if ($str !== '') {
            $this->emitStr($str, $sl);
        }

        $this->sourceMapState->incGeneratedLines();
        $this->sourceMapState->setGeneratedColumns(0);

        echo PHP_EOL;
    }

    public function emitStr(string $str, ?SourceLocation $sl = null): void
    {
        if ($this->sourceMapState->getGeneratedColumns() === 0) {
            $this->sourceMapState->incGeneratedColumns($this->indentLevel * 2);
            echo str_repeat(' ', $this->indentLevel * 2);
        }

        if ($this->enableSourceMaps && $sl instanceof SourceLocation) {
            $this->sourceMapState->addMapping(
                [
                    'source' => $sl->getFile(),
                    'original' => [
                        'line' => $sl->getLine() - 1,
                        'column' => $sl->getColumn(),
                    ],
                    'generated' => [
                        'line' => $this->sourceMapState->getGeneratedLines(),
                        'column' => $this->sourceMapState->getGeneratedColumns(),
                    ],
                ],
            );
        }

        $this->sourceMapState->incGeneratedColumns(strlen($str));

        echo $str;
    }

    public function emitArgList(array $nodes, ?SourceLocation $sepLoc, string $sep = ', '): void
    {
        $nodesCount = count($nodes);
        foreach ($nodes as $i => $arg) {
            $this->emitNode($arg);

            if ($i < $nodesCount - 1) {
                $this->emitStr($sep, $sepLoc);
            }
        }
    }

    public function emitContextPrefix(NodeEnvironmentInterface $env, ?SourceLocation $sl = null): void
    {
        if ($env->isContext(NodeEnvironment::CONTEXT_RETURN)) {
            $this->emitStr('return ', $sl);
        }
    }

    public function emitContextSuffix(NodeEnvironmentInterface $env, ?SourceLocation $sl = null): void
    {
        if (!$env->isContext(NodeEnvironment::CONTEXT_EXPRESSION)) {
            $this->emitStr(';', $sl);
        }
    }

    public function emitFnWrapPrefix(NodeEnvironmentInterface $env, ?SourceLocation $sl = null): void
    {
        $this->emitStr('(function()', $sl);
        if ($env->getLocals() !== []) {
            $this->emitStr(' use(', $sl);

            foreach (array_values($env->getLocals()) as $i => $l) {
                $shadowed = $env->getShadowed($l);
                if ($shadowed instanceof Symbol) {
                    $this->emitPhpVariable($shadowed, $sl);
                } else {
                    $this->emitPhpVariable($l, $sl);
                }

                if ($i < count($env->getLocals()) - 1) {
                    $this->emitStr(',', $sl);
                }
            }

            $this->emitStr(')', $sl);
        }

        $this->emitLine(' {', $sl);
        $this->increaseIndentLevel();
    }

    public function emitPhpVariable(
        Symbol $symbol,
        ?SourceLocation $loc = null,
        bool $asReference = false,
        bool $isVariadic = false,
    ): void {
        if (is_null($loc)) {
            $loc = $symbol->getStartLocation();
        }

        $refPrefix = $asReference ? '&' : '';
        $variadicPrefix = $isVariadic ? '...' : '';
        $this->emitStr($variadicPrefix . $refPrefix . '$' . $this->mungeEncode($symbol->getName()), $loc);
    }

    public function mungeEncode(string $str): string
    {
        return $this->munge->encode($str);
    }

    public function mungeEncodeNs(string $str): string
    {
        return $this->munge->encodeNs($str);
    }

    public function emitFnWrapSuffix(?SourceLocation $sl = null): void
    {
        $this->decreaseIndentLevel();
        $this->emitLine();
        $this->emitStr('})()', $sl);
    }

    public function emitLiteral(array|bool|float|int|TypeInterface|string|null $value): void
    {
        (new LiteralEmitter($this, $this->printer))->emitLiteral($value);
    }

    public function increaseIndentLevel(): void
    {
        ++$this->indentLevel;
    }

    public function decreaseIndentLevel(): void
    {
        --$this->indentLevel;
    }
}
