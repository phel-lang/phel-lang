<?php

declare(strict_types=1);

namespace Phel;

use Exception;
use Phel\Ast\Node;
use Phel\Emitter\LiteralEmitter;
use Phel\Emitter\NodeEmitterFactory;
use Phel\Lang\AbstractType;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;
use Phel\SourceMap\SourceMapGenerator;
use RuntimeException;
use Throwable;

final class Emitter
{
    private bool $enableSourceMaps;
    private NodeEmitterFactory $nodeEmitterFactory;
    private SourceMapGenerator $sourceMapGenerator;
    private int $indentLevel = 0;
    private int $generatedLines = 0;
    private int $generatedColumns = 0;
    private array $sourceMap = [];

    public function __construct(bool $enableSourceMaps = true)
    {
        $this->enableSourceMaps = $enableSourceMaps;
        $this->nodeEmitterFactory = new NodeEmitterFactory($this);
        $this->sourceMapGenerator = new SourceMapGenerator();
    }

    public function emitNodeAndEval(Node $node): string
    {
        $code = $this->emitNodeAsString($node);
        $this->eval($code);

        return $code;
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
        $nodeEmitter = $this->nodeEmitterFactory->createNodeEmitter(get_class($node));
        $nodeEmitter->emit($node);
    }

    /**
     * Evaluates the code and returns the evaluated value.
     *
     * @return mixed
     */
    public function eval(string $code)
    {
        $filename = tempnam(sys_get_temp_dir(), '__phel');
        if (!$filename) {
            throw new Exception('can not create temp file.');
        }

        try {
            file_put_contents($filename, "<?php\n" . $code);
            if (file_exists($filename)) {
                return require $filename;
            }

            throw new RuntimeException('Can not require file: ' . $filename);
        } catch (Throwable $e) {
            throw $e;
        }
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
            '$GLOBALS["__phel"]["' . addslashes(Munge::encodeNs($namespace)) . '"]["' . addslashes($name->getName()) . '"]',
            $name->getStartLocation()
        );
    }

    public function emitGlobalBaseMeta(string $namespace, Symbol $name): void
    {
        $this->emitStr(
            '$GLOBALS["__phel_meta"]["' . addslashes(Munge::encodeNs($namespace)) . '"]["' . addslashes($name->getName()) . '"]',
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
        $this->emitStr($variadicPrefix . $refPrefix . '$' . $this->munge($m->getName()), $loc);
    }

    public function munge(string $s): string
    {
        return Munge::encode($s);
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
