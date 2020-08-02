<?php

declare(strict_types=1);

namespace Phel;

use Exception;
use Phel\Ast\Node;
use Phel\Emitter\NodeEmitterFactory;
use Phel\Lang\AbstractType;
use Phel\Lang\Keyword;
use Phel\Lang\PhelArray;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;
use Phel\Lang\Table;
use Phel\Lang\Tuple;
use Phel\SourceMap\SourceMapGenerator;
use Throwable;

final class Emitter
{
    private SourceMapGenerator $sourceMapGenerator;

    private bool $enableSourceMaps;

    public int $indentLevel = 0;

    private int $generatedLines = 0;

    private int $generatedColumns = 0;

    private array $sourceMap = [];

    private NodeEmitterFactory $nodeEmitterFactory;

    public function __construct(bool $enableSourceMaps = true)
    {
        $this->enableSourceMaps = $enableSourceMaps;
        $this->sourceMapGenerator = new SourceMapGenerator();
        $this->nodeEmitterFactory = new NodeEmitterFactory();
    }

    public function emitAndEval(Node $node): string
    {
        $code = $this->emitAsString($node);
        $this->eval($code);

        return $code;
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

            throw new \RuntimeException('Can not require file: ' . $filename);
        } catch (Throwable $e) {
            throw $e;
        }
    }

    public function emitAsString(Node $node): string
    {
        $this->generatedLines = 0;
        $this->generatedColumns = 0;
        $this->indentLevel = 0;
        $this->sourceMap = [];

        ob_start();
        $this->emit($node);
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

    public function emit(Node $node): void
    {
        $nodeEmitter = $this->nodeEmitterFactory->createNodeEmitter($this, get_class($node));
        $nodeEmitter->emit($node);
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

    public function emitFinally(Node $node): void
    {
        $this->emitLine(' finally {', $node->getStartSourceLocation());
        $this->indentLevel++;
        $this->emit($node);
        $this->indentLevel--;
        $this->emitLine();
        $this->emitStr('}', $node->getStartSourceLocation());
    }

    public function emitArgList(array $nodes, ?SourceLocation $sepLoc, string $sep = ', '): void
    {
        $nodesCount = count($nodes);
        foreach ($nodes as $i => $arg) {
            $this->emit($arg);

            if ($i < $nodesCount - 1) {
                $this->emitStr($sep, $sepLoc);
            }
        }
    }

    /**
     * Emits a Phel value.
     *
     * @param AbstractType|scalar|null $x The value
     */
    public function emitPhel($x): void
    {
        if (is_float($x)) {
            $this->emitStr($this->printFloat($x));
        } elseif (is_int($x)) {
            $this->emitStr((string)$x);
        } elseif (is_string($x)) {
            $this->emitStr(Printer::readable()->print($x));
        } elseif ($x === null) {
            $this->emitStr('null');
        } elseif (is_bool($x)) {
            $this->emitStr($x === true ? 'true' : 'false');
        } elseif ($x instanceof Keyword) {
            $this->emitStr('new \Phel\Lang\Keyword("' . addslashes($x->getName()) . '")', $x->getStartLocation());
        } elseif ($x instanceof Symbol) {
            $this->emitStr(
                '(\Phel\Lang\Symbol::create("' . addslashes($x->getFullName()) . '"))',
                $x->getStartLocation()
            );
        } elseif ($x instanceof PhelArray) {
            $this->emitStr('\Phel\Lang\PhelArray::create(', $x->getStartLocation());
            if (count($x) > 0) {
                $this->indentLevel++;
                $this->emitLine();
            }

            foreach ($x as $i => $value) {
                $this->emitPhel($value);

                if ($i < count($x) - 1) {
                    $this->emitStr(',', $x->getStartLocation());
                }

                $this->emitLine();
            }

            if (count($x) > 0) {
                $this->indentLevel--;
            }

            $this->emitStr(')', $x->getStartLocation());
        } elseif ($x instanceof Table) {
            $this->emitStr('\Phel\Lang\Table::fromKVs(', $x->getStartLocation());
            if (count($x) > 0) {
                $this->indentLevel++;
                $this->emitLine();
            }

            $i = 0;
            foreach ($x as $key => $value) {
                $this->emitPhel($key);
                $this->emitStr(', ', $x->getStartLocation());
                $this->emitPhel($value);

                if ($i < count($x) - 1) {
                    $this->emitStr(',', $x->getStartLocation());
                }
                $this->emitLine();

                $i++;
            }

            if (count($x) > 0) {
                $this->indentLevel--;
            }
            $this->emitStr(')', $x->getStartLocation());
        } elseif ($x instanceof Tuple) {
            if ($x->isUsingBracket()) {
                $this->emitStr('\Phel\Lang\Tuple::createBracket(', $x->getStartLocation());
            } else {
                $this->emitStr('\Phel\Lang\Tuple::create(', $x->getStartLocation());
            }

            if (count($x) > 0) {
                $this->indentLevel++;
                $this->emitLine();
            }

            foreach ($x as $i => $value) {
                $this->emitPhel($value);

                if ($i < count($x) - 1) {
                    $this->emitStr(',', $x->getStartLocation());
                }

                $this->emitLine();
            }

            if (count($x) > 0) {
                $this->indentLevel--;
            }

            $this->emitStr(')', $x->getStartLocation());
        } else {
            throw new \Exception('literal not supported: ' . gettype($x));
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
        $this->indentLevel++;
    }

    public function emitFnWrapSuffix(NodeEnvironment $env, ?SourceLocation $sl = null): void
    {
        $this->indentLevel--;
        $this->emitLine();
        $this->emitStr('})()', $sl);
    }

    private function printFloat(float $x): string
    {
        if ((int)$x == $x) {
            // (string) 10.0 will return 10 and not 10.0
            // so we just add a .0 at the end
            return ((string)$x) . '.0';
        }

        return ((string)$x);
    }

    public function munge(string $s): string
    {
        return Munge::encode($s);
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
}
