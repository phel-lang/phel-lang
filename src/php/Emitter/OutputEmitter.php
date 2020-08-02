<?php

declare(strict_types=1);

namespace Phel\Emitter;

use Phel\Ast\Node;
use Phel\Emitter\OutputEmitter\LiteralEmitter;
use Phel\Emitter\OutputEmitter\Munge;
use Phel\Emitter\OutputEmitter\NodeEmitterFactory;
use Phel\Emitter\OutputEmitter\SourceMap\SourceMapGenerator;
use Phel\Lang\AbstractType;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;
use Phel\NodeEnvironment;

final class OutputEmitter
{
    public int $indentLevel = 0;
    public int $generatedLines = 0;
    public int $generatedColumns = 0;
    public array $sourceMap = [];

    private bool $enableSourceMaps;
    private SourceMapGenerator $sourceMapGenerator;
    private NodeEmitterFactory $nodeEmitterFactory;
    private Munge $munge;

    public function __construct(
        bool $enableSourceMaps,
        SourceMapGenerator $sourceMapGenerator,
        NodeEmitterFactory $nodeEmitterFactory,
        Munge $munge
    ) {
        $this->enableSourceMaps = $enableSourceMaps;
        $this->sourceMapGenerator = $sourceMapGenerator;
        $this->nodeEmitterFactory = $nodeEmitterFactory;
        $this->munge = $munge;
    }

    public function emitNodeAsString(Node $node): string
    {
        $this->generatedLines = 0;
        $this->generatedColumns = 0;
        $this->indentLevel = 0;
        $this->sourceMap = [];

        ob_start();
        $this->emitNode($node);
        $code = ob_get_contents();
        ob_end_clean();

        if (!$this->enableSourceMaps) {
            return $code;
        }

        $sourceMap = $this->sourceMapGenerator->encode($this->sourceMap);
        $file = $node->getStartSourceLocation()
            ? $node->getStartSourceLocation()->getFile()
            : 'string';

        return (
            '// ' . $file . "\n"
            . '// ;;' . $sourceMap . "\n"
            . $code
        );
    }

    public function emitNode(Node $node): void
    {
        $nodeEmitter = $this->nodeEmitterFactory->createNodeEmitter($this, get_class($node));
        $nodeEmitter->emit($node);
    }

    public function emitLine(string $str = '', ?SourceLocation $sl = null): void
    {
        if ('' !== $str) {
            $this->emitStr($str, $sl);
        }

        $this->generatedLines++;
        $this->generatedColumns = 0;

        echo PHP_EOL;
    }

    public function emitStr(string $str, ?SourceLocation $sl = null): void
    {
        if ($this->generatedColumns === 0) {
            $this->generatedColumns += $this->indentLevel * 2;
            echo str_repeat(' ', $this->indentLevel * 2);
        }

        if ($this->enableSourceMaps && $sl) {
            $this->sourceMap[] = [
                'source' => $sl->getFile(),
                'original' => [
                    'line' => $sl->getLine() - 1,
                    'column' => $sl->getColumn(),
                ],
                'generated' => [
                    'line' => $this->generatedLines,
                    'column' => $this->generatedColumns,
                ],
            ];
        }

        $this->generatedColumns += strlen($str);

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

    public function emitGlobalBase(string $namespace, Symbol $name): void
    {
        $this->emitStr(
            '$GLOBALS["__phel"]["' . addslashes($this->mungeEncodeNs($namespace)) . '"]["' . addslashes($name->getName()) . '"]',
            $name->getStartLocation()
        );
    }

    public function emitGlobalBaseMeta(string $namespace, Symbol $name): void
    {
        $this->emitStr(
            '$GLOBALS["__phel_meta"]["' . addslashes($this->mungeEncodeNs($namespace)) . '"]["' . addslashes($name->getName()) . '"]',
            $name->getStartLocation()
        );
    }

    public function emitContextPrefix(NodeEnvironment $env, ?SourceLocation $sl = null): void
    {
        if ($env->getContext() === NodeEnvironment::CTX_RET) {
            $this->emitStr('return ', $sl);
        }
    }

    public function emitContextSuffix(NodeEnvironment $env, ?SourceLocation $sl = null): void
    {
        if ($env->getContext() !== NodeEnvironment::CTX_EXPR) {
            $this->emitStr(';', $sl);
        }
    }

    public function emitFnWrapPrefix(NodeEnvironment $env, ?SourceLocation $sl = null): void
    {
        $this->emitStr('(function()', $sl);
        if (count($env->getLocals()) > 0) {
            $this->emitStr(' use(', $sl);

            foreach (array_values($env->getLocals()) as $i => $l) {
                $shadowed = $env->getShadowed($l);
                if ($shadowed) {
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
        Symbol $m,
        ?SourceLocation $loc = null,
        bool $asReference = false,
        bool $isVariadic = false
    ): void {
        if (is_null($loc)) {
            $loc = $m->getStartLocation();
        }
        $refPrefix = $asReference ? '&' : '';
        $variadicPrefix = $isVariadic ? '...' : '';
        $this->emitStr($variadicPrefix . $refPrefix . '$' . $this->mungeEncode($m->getName()), $loc);
    }

    public function mungeEncode(string $s): string
    {
        return $this->munge->encode($s);
    }

    public function mungeEncodeNs(string $s): string
    {
        return $this->munge->encodeNs($s);
    }

    public function mungeDecodeNs(string $s): string
    {
        return $this->munge->decodeNs($s);
    }

    public function emitFnWrapSuffix(?SourceLocation $sl = null): void
    {
        $this->decreaseIndentLevel();
        $this->emitLine();
        $this->emitStr('})()', $sl);
    }

    /**
     * @param AbstractType|scalar|null $x The value
     */
    public function emitLiteral($x): void
    {
        (new LiteralEmitter($this))->emitLiteral($x);
    }

    public function increaseIndentLevel(): void
    {
        $this->indentLevel++;
    }

    public function decreaseIndentLevel(): void
    {
        $this->indentLevel--;
    }
}
