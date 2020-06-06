<?php

namespace Phel;

use Exception;
use ParseError;
use Phel\Ast\ApplyNode;
use Phel\Ast\ArrayNode;
use Phel\Ast\CallNode;
use Phel\Ast\CatchNode;
use Phel\Ast\Node;
use Phel\Ast\DefNode;
use Phel\Ast\DefStructNode;
use Phel\Ast\DoNode;
use Phel\Ast\FnNode;
use Phel\Ast\ForeachNode;
use Phel\Ast\GlobalVarNode;
use Phel\Ast\IfNode;
use Phel\Ast\LetNode;
use Phel\Ast\LiteralNode;
use Phel\Ast\LocalVarNode;
use Phel\Ast\MethodCallNode;
use Phel\Ast\NsNode;
use Phel\Ast\PhpArrayGetNode;
use Phel\Ast\PhpArrayPushNode;
use Phel\Ast\PhpArraySetNode;
use Phel\Ast\PhpArrayUnsetNode;
use Phel\Ast\PhpClassNameNode;
use Phel\Ast\PhpNewNode;
use Phel\Ast\PhpObjectCallNode;
use Phel\Ast\PhpVarNode;
use Phel\Ast\PropertyOrConstantAccessNode;
use Phel\Ast\QuoteNode;
use Phel\Ast\RecurNode;
use Phel\Ast\TableNode;
use Phel\Ast\ThrowNode;
use Phel\Ast\TryNode;
use Phel\Ast\TupleNode;
use Phel\Lang\Keyword;
use Phel\Lang\Phel;
use Phel\Lang\PhelArray;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;
use Phel\Lang\Table;
use Phel\Lang\Tuple;
use Phel\SourceMap\SourceMapGenerator;
use Throwable;

class Emitter {

    /**
     * @var SourceMapGenerator
     */
    private $sourceMapGenerator;

    /**
     * @var bool
     */
    private $enableSourceMaps = true;

    /**
     * @var int
     */
    private $indentLevel = 0;

    /**
     * @var int
     */
    private $generatedLines = 0;

    /**
     * @var int
     */
    private $generatedColumns = 0;

    /**
     * @var array
     */
    private $sourceMap = [];

    public function __construct($enableSourceMaps = true)
    {
        $this->enableSourceMaps = $enableSourceMaps;
        $this->sourceMapGenerator = new SourceMapGenerator();
    }

    public function emitAndEval(Node $node): string {
        $this->generatedLines = 0;
        $this->generatedColumns = 0;
        $this->indentLevel = 0;
        $this->sourceMap = [];

        $code = $this->emitAsString($node);
        $filename = tempnam(sys_get_temp_dir(), '__phel');
        if ($filename) {
            file_put_contents($filename, "<?php\n" . $code);
            try {
                require $filename;
            } catch (Throwable $e) {
                throw $e;
            }
        } else {
            throw new Exception("can not create temp file.");
        }
        
        return $code;
    }

    public function emitAsString(Node $node) {
        $this->generatedLines = 0;
        $this->generatedColumns = 0;
        $this->indentLevel = 0;
        $this->sourceMap = [];

        ob_start();
        $this->emit($node);
        $code = ob_get_contents();
        ob_end_clean();

        if ($this->enableSourceMaps) {
            $sourceMap = $this->sourceMapGenerator->encode($this->sourceMap);
            $file = $node->getStartSourceLocation() 
                ? $node->getStartSourceLocation()->getFile() 
                : 'string';

            return (
                '// ' . $file . "\n"
                . '// ;;' . $sourceMap . "\n"
                . $code
            );
        } else {
            return $code;
        }
    }

    protected function emit(Node $node) {
        $nodeClass = get_class($node);
        switch ($nodeClass) {
            case NsNode::class: 
                $this->emitNs($node); break;
            case DefNode::class: 
                $this->emitDef($node); break;
            case LiteralNode::class: 
                $this->emitLiteral($node); break;
            case QuoteNode::class: 
                $this->emitQuote($node); break;
            case FnNode::class: 
                $this->emitFnAsClass($node); break;
            case DoNode::class: 
                $this->emitDo($node); break;
            case LetNode::class: 
                $this->emitLet($node); break;
            case LocalVarNode::class: 
                $this->emitLocalVar($node); break;
            case GlobalVarNode::class: 
                $this->emitGlobalVar($node); break;
            case CallNode::class: 
                $this->emitCall($node); break;
            case IfNode::class: 
                $this->emitIf($node); break;
            case ApplyNode::class: 
                $this->emitApply($node); break;
            case TupleNode::class: 
                $this->emitTuple($node); break;
            case PhpNewNode::class: 
                $this->emitPhpNew($node); break;
            case PhpVarNode::class: 
                $this->emitPhpVar($node); break;
            case PhpObjectCallNode::class: 
                $this->emitObjectCall($node); break;
            case RecurNode::class: 
                $this->emitRecur($node); break;
            case ThrowNode::class: 
                $this->emitThrow($node); break;
            case TryNode::class:
                $this->emitTry($node); break;
            case CatchNode::class: 
                $this->emitCatch($node); break;
            case PhpArrayGetNode::class: 
                $this->emitPhpArrayGet($node); break;
            case PhpArraySetNode::class: 
                $this->emitPhpArraySet($node); break;
            case PhpArrayUnsetNode::class: 
                $this->emitPhpArrayUnset($node); break;
            case PhpClassNameNode::class: 
                $this->emitClassName($node); break;
            case PhpArrayPushNode::class: 
                $this->emitPhpArrayPush($node); break;
            case ForeachNode::class: 
                $this->emitForeach($node); break;
            case ArrayNode::class: 
                $this->emitArray($node); break;
            case TableNode::class: 
                $this->emitTable($node); break;
            case DefStructNode::class:
                $this->emitDefStruct($node); break;
            default:
                throw new \Exception('Unexpected node: ' . get_class($node));
        }
    }

    protected function emitDefStruct(DefStructNode $node) {
        $paramCount = count($node->getParams());
        $this->emitLine('namespace ' . $node->getNamespace() . ';', $node->getStartSourceLocation());
        $this->emitLine('class ' . $this->munge($node->getName()->getName()) . ' extends \Phel\Lang\Struct {', $node->getStartSourceLocation());
        $this->indentLevel++;

        // Constructor
        $this->emitStr('public function __construct(', $node->getStartSourceLocation());
        foreach ($node->getParams() as $i => $param) {
            $this->emitPhpVariable($param);

            if ($i < $paramCount - 1) {
                $this->emitStr(', ', $node->getStartSourceLocation());
            }
        }
        $this->emitLine(') {', $node->getStartSourceLocation());
        $this->indentLevel++;
        foreach ($node->getParams() as $i => $param) {
            $keyword = new Keyword($param->getName());
            $keyword->setStartLocation($node->getStartSourceLocation());

            $this->emitStr('$this->offsetSet(', $node->getStartSourceLocation());
            $this->emitPhel($keyword);
            $this->emitStr(', ', $node->getStartSourceLocation());
            $this->emitPhpVariable($param);
            $this->emitLine(');', $node->getStartSourceLocation());
        }
        $this->indentLevel--;
        $this->emitLine('}', $node->getStartSourceLocation());

        // Get Allowed Keys Function
        $this->emitStr('public function getAllowedKeys(', $node->getStartSourceLocation());
        $this->emitLine('): array {', $node->getStartSourceLocation());
        $this->indentLevel++;
        $this->emitStr('return [', $node->getStartSourceLocation());
        foreach ($node->getParamsAsKeywords() as $i => $keyword) {
            $this->emitPhel($keyword);

            if ($i < $paramCount - 1) {
                $this->emitStr(', ', $node->getStartSourceLocation());
            }
        }
        $this->emitLine('];', $node->getStartSourceLocation());
        $this->indentLevel--;
        $this->emitLine('}', $node->getStartSourceLocation());

        // End of class
        $this->indentLevel--;
        $this->emitLine('}', $node->getStartSourceLocation());
    }

    protected function emitPhpVariable(Symbol $m, SourceLocation $loc = null, $asReference = false, $isVariadic = false) {
        if (is_null($loc)) {
            $loc = $m->getStartLocation();
        }
        $refPrefix = $asReference ? '&' : '';
        $variadicPrefix = $isVariadic ? '...' : '';
        $this->emitStr($variadicPrefix . $refPrefix . '$' . $this->munge($m->getName()), $loc);
    }

    protected function emitForeach(ForeachNode $node) {
        if ($node->getEnv()->getContext() !== NodeEnvironment::CTX_STMT) {
            $this->emitContextPrefix($node->getEnv(), $node->getStartSourceLocation());
            $this->emitFnWrapPrefix($node->getEnv(), $node->getStartSourceLocation());
        }

        $this->emitStr('foreach ((', $node->getStartSourceLocation());
        $this->emit($node->getListExpr());
        $this->emitStr(' ?? []) as ', $node->getStartSourceLocation());
        if ($node->getKeySymbol()) {
            $this->emitPhpVariable($node->getKeySymbol());
            $this->emitStr(' => ', $node->getStartSourceLocation());
        }
        $this->emitPhpVariable($node->getValueSymbol());
        $this->emitLine(') {', $node->getStartSourceLocation());
        $this->indentLevel++;
        $this->emit($node->getBodyExpr());
        $this->indentLevel--;
        $this->emitLine();
        $this->emitStr('}', $node->getStartSourceLocation());

        if ($node->getEnv()->getContext() !== NodeEnvironment::CTX_STMT) {
            $this->emitLine();
            $this->emitStr('return null;', $node->getStartSourceLocation());
            $this->emitFnWrapSuffix($node->getEnv(), $node->getStartSourceLocation());
            $this->emitContextSuffix($node->getEnv(), $node->getStartSourceLocation());
        }
    }

    protected function emitClassName(PhpClassNameNode $node) {
        $this->emitStr($node->getName()->getName(), $node->getName()->getStartLocation());
    }

    protected function emitPhpArrayUnset(PhpArrayUnsetNode $node) {
        $this->emitContextPrefix($node->getEnv(), $node->getStartSourceLocation());
        $this->emitStr('unset((', $node->getStartSourceLocation());
        $this->emit($node->getArrayExpr());
        $this->emitStr(')[(', $node->getStartSourceLocation());
        $this->emit($node->getAccessExpr());
        $this->emitStr(')])', $node->getStartSourceLocation());
        $this->emitContextSuffix($node->getEnv(), $node->getStartSourceLocation());
    }

    protected function emitPhpArrayGet(PhpArrayGetNode $node) {
        $this->emitContextPrefix($node->getEnv(), $node->getStartSourceLocation());
        $this->emitStr('((', $node->getStartSourceLocation());
        $this->emit($node->getArrayExpr());
        $this->emitStr(')[(', $node->getStartSourceLocation());
        $this->emit($node->getAccessExpr());
        $this->emitStr(')] ?? null)', $node->getStartSourceLocation());
        $this->emitContextSuffix($node->getEnv(), $node->getStartSourceLocation());
    }

    protected function emitPhpArraySet(PhpArraySetNode $node) {
        $this->emitContextPrefix($node->getEnv(), $node->getStartSourceLocation());
        $this->emitStr('(', $node->getStartSourceLocation());
        $this->emit($node->getArrayExpr());
        $this->emitStr(')[(', $node->getStartSourceLocation());
        $this->emit($node->getAccessExpr());
        $this->emitStr(')] = ', $node->getStartSourceLocation());
        $this->emit($node->getValueExpr());
        $this->emitContextSuffix($node->getEnv(), $node->getStartSourceLocation());
    }

    protected function emitPhpArrayPush(PhpArrayPushNode $node) {
        $this->emitContextPrefix($node->getEnv(), $node->getStartSourceLocation());
        $this->emitStr('(', $node->getStartSourceLocation());
        $this->emit($node->getArrayExpr());
        $this->emitStr(')[] = ', $node->getStartSourceLocation());
        $this->emit($node->getValueExpr());
        $this->emitContextSuffix($node->getEnv(), $node->getStartSourceLocation());
    }

    protected function emitTry(TryNode $node) {
        if ($node->getFinally() || count($node->getCatches()) > 0) {
            if ($node->getEnv()->getContext() == NodeEnvironment::CTX_EXPR) {
                $this->emitFnWrapPrefix($node->getEnv(), $node->getStartSourceLocation());
            }

            $this->emitLine('try {', $node->getStartSourceLocation());
            $this->indentLevel++;
            $this->emit($node->getBody());
            $this->indentLevel--;
            $this->emitLine();
            $this->emitStr('}', $node->getStartSourceLocation());

            foreach ($node->getCatches() as $catchNode) {
                $this->emit($catchNode);
            }

            if ($node->getFinally()) {
                $this->emitFinally($node->getFinally());
            }

            if ($node->getEnv()->getContext() == NodeEnvironment::CTX_EXPR) {
                $this->emitFnWrapSuffix($node->getEnv(), $node->getStartSourceLocation());
            }
        } else {
            $this->emit($node->getBody());
        }
    }

    protected function emitCatch(CatchNode $node) {
        $this->emitStr(' catch (', $node->getStartSourceLocation());
        $this->emitStr($node->getType()->getName(), $node->getType()->getStartLocation());
        $this->emitStr(' $' . $this->munge($node->getName()->getName()), $node->getName()->getStartLocation());
        $this->emitLine(') {', $node->getStartSourceLocation());
        $this->indentLevel++;
        $this->emit($node->getBody());
        $this->indentLevel--;
        $this->emitLine();
        $this->emitStr('}', $node->getStartSourceLocation());
    }

    protected function emitFinally(Node $node) {
        $this->emitLine(' finally {', $node->getStartSourceLocation());
        $this->indentLevel++;
        $this->emit($node);
        $this->indentLevel--;
        $this->emitLine();
        $this->emitStr('}', $node->getStartSourceLocation());
    }

    protected function emitThrow(ThrowNode $node) {
        if ($node->getEnv()->getContext() == NodeEnvironment::CTX_EXPR) {
            $this->emitFnWrapPrefix($node->getEnv(), $node->getStartSourceLocation());
        }

        $this->emitStr('throw ', $node->getStartSourceLocation());
        $this->emit($node->getExceptionExpr());
        $this->emitStr(';', $node->getStartSourceLocation());

        if ($node->getEnv()->getContext() == NodeEnvironment::CTX_EXPR) {
            $this->emitFnWrapSuffix($node->getEnv(), $node->getStartSourceLocation());
        }
    }

    protected function emitRecur(RecurNode $node) {
        $params = $node->getFrame()->getParams();
        $exprs = $node->getExprs();
        $env = $node->getEnv();

        $tempSyms = [];
        foreach ($exprs as $i => $expr) {
            $tempSym = Symbol::gen();
            $tempSyms[] = $tempSym;

            $this->emitPhpVariable($tempSym, $node->getStartSourceLocation());
            $this->emitStr(' = ', $node->getStartSourceLocation());
            $this->emit($expr);
            $this->emitLine(';', $node->getStartSourceLocation());
        }

        
        foreach ($tempSyms as $i => $tempSym) {
            $paramSym = $params[$i];
            $loc = $paramSym->getStartLocation();
            $shadowedSym = $env->getShadowed($paramSym);
            if ($shadowedSym) {
                $paramSym = $shadowedSym;
            }

            $this->emitPhpVariable($paramSym, $loc);
            $this->emitStr(' = ', $node->getStartSourceLocation());
            $this->emitPhpVariable($tempSym, $node->getStartSourceLocation());
            $this->emitLine(';', $node->getStartSourceLocation());
        }

        $this->emitLine('continue;', $node->getStartSourceLocation());
    }

    protected function emitNs(NsNode $node) {
        $nsSym = new Symbol("*ns*");
        $nsSym->setStartLocation($node->getStartSourceLocation());
        $this->emitGlobalBase("phel\\core", $nsSym);
        $this->emitStr(" = ", $node->getStartSourceLocation());
        $this->emitPhel("\\" . $node->getNamespace());
        $this->emitLine(";", $node->getStartSourceLocation());

        foreach ($node->getRequireNs() as $i => $ns) {
            $this->emitStr('\Phel\Runtime::getInstance()->loadNs("' . \addslashes($ns->getName()) . '");', $ns->getStartLocation());
            if ($i < count($node->getRequireNs()) - 1) {
                $this->emitLine();
            }
        }
    }

    protected function emitObjectCall(PhpObjectCallNode $node) {
        $fnCode = $node->isStatic() ? '::' : '->';
        $targetExpr = $node->getTargetExpr();
        $callExpr = $node->getCallExpr();

        $this->emitContextPrefix($node->getEnv(), $node->getStartSourceLocation());

        if ($node->isStatic() && $targetExpr instanceof PhpClassNameNode) {
            $this->emitStr('(', $node->getStartSourceLocation());
            $this->emitClassName($targetExpr);
            $this->emitStr($fnCode, $node->getStartSourceLocation());
        } else {
            $this->emitFnWrapPrefix($node->getEnv(), $node->getStartSourceLocation());

            $targetSym = Symbol::gen('target_');
            $this->emitPhpVariable($targetSym, $node->getStartSourceLocation());
            $this->emitStr(' = ', $node->getStartSourceLocation());
            $this->emit($targetExpr);
            $this->emitLine(';', $node->getStartSourceLocation());

            $this->emitStr('return ', $node->getStartSourceLocation());
            $this->emitPhpVariable($targetSym, $node->getStartSourceLocation());
            $this->emitStr($fnCode, $node->getStartSourceLocation());
        }

        // Method/Property and Arguments
        if ($callExpr instanceof MethodCallNode) {
            $this->emitStr($callExpr->getFn()->getName(), $callExpr->getFn()->getStartLocation());
            $this->emitStr('(', $node->getStartSourceLocation());
            $this->emitArgList($callExpr->getArgs(), $node->getStartSourceLocation());
            $this->emitStr(')', $node->getStartSourceLocation());
        } else if ($callExpr instanceof PropertyOrConstantAccessNode) {
            $this->emitStr($callExpr->getName()->getName(), $callExpr->getName()->getStartLocation());
        } else {
            throw new \Exception('Not supported ' . get_class($callExpr));
        }

        // Close Expression
        if ($node->isStatic() && $targetExpr instanceof PhpClassNameNode) {
            $this->emitStr(')', $node->getStartSourceLocation());
        } else {
            $this->emitStr(';', $node->getStartSourceLocation());
            $this->emitFnWrapSuffix($node->getEnv(), $node->getStartSourceLocation());
        }

        $this->emitContextSuffix($node->getEnv(), $node->getStartSourceLocation());
    }

    protected function emitPhpVar(PhpVarNode $node) {
        $this->emitContextPrefix($node->getEnv(), $node->getStartSourceLocation());

        if ($node->isCallable()) {
            $this->emitStr('(function(...$args) { return ' . $node->getName() . '(...$args);' . '})', $node->getStartSourceLocation());
        } else {
            $this->emitStr($node->getName(), $node->getStartSourceLocation());
        }

        $this->emitContextSuffix($node->getEnv(), $node->getStartSourceLocation());
    }

    protected function emitPhpNew(PhpNewNode $node) {
        $this->emitContextPrefix($node->getEnv(), $node->getStartSourceLocation());

        $classExpr = $node->getClassExpr();

        if ($classExpr instanceof PhpClassNameNode) {
            $this->emitStr('(new ', $node->getStartSourceLocation());
            $this->emitClassName($classExpr);
            $this->emitStr('(', $node->getStartSourceLocation());
        } else {
            $this->emitFnWrapPrefix($node->getEnv(), $node->getStartSourceLocation());

            $targetSym = Symbol::gen('target_');
            $this->emitPhpVariable($targetSym, $node->getStartSourceLocation());
            $this->emitStr(' = ', $node->getStartSourceLocation());
            $this->emit($classExpr);
            $this->emitLine(';', $node->getStartSourceLocation());

            $this->emitStr('return new $' . $targetSym->getName() . '(', $node->getStartSourceLocation());
        }

        // Args
        $this->emitArgList($node->getArgs(), $node->getStartSourceLocation());

        if ($classExpr instanceof PhpClassNameNode) {
            $this->emitStr('))', $node->getStartSourceLocation());
        } else {
            $this->emitStr(');', $node->getStartSourceLocation());
            $this->emitFnWrapSuffix($node->getEnv(), $node->getStartSourceLocation());
        }

        $this->emitContextSuffix($node->getEnv(), $node->getStartSourceLocation());
    }

    protected function emitTuple(TupleNode $node) {
        $this->emitContextPrefix($node->getEnv(), $node->getStartSourceLocation());
        $this->emitStr('\Phel\Lang\Tuple::createBracket(', $node->getStartSourceLocation());
        $this->emitArgList($node->getArgs(), $node->getStartSourceLocation());
        $this->emitStr(')', $node->getStartSourceLocation());
        $this->emitContextSuffix($node->getEnv(), $node->getStartSourceLocation());
    }

    protected function emitApply(ApplyNode $node) {
        $this->emitContextPrefix($node->getEnv(), $node->getStartSourceLocation());

        $fnNode = $node->getFn();

        if ($fnNode instanceof PhpVarNode && $fnNode->isInfix()) {
            $this->emitStr('array_reduce([', $node->getStartSourceLocation());
            // Args
            $argCount = count($node->getArguments());
            foreach ($node->getArguments() as $i => $arg) {
                if ($i < $argCount - 1) {
                    $this->emit($arg);
                    $this->emitStr(', ', $node->getStartSourceLocation());
                } else {
                    $this->emitStr('...((', $node->getStartSourceLocation());
                    $this->emit($arg);
                    $this->emitStr(') ?? [])', $node->getStartSourceLocation());
                }
            }
            $this->emitStr('], function($a, $b) { return ($a ', $node->getStartSourceLocation());
            $this->emitStr($fnNode->getName(), $fnNode->getStartSourceLocation());
            $this->emitStr(' $b); })', $node->getStartSourceLocation());
        } else {
            if ($fnNode instanceof PhpVarNode) {
                $this->emitStr($fnNode->getName(), $fnNode->getStartSourceLocation());
            } else {
                $this->emitStr('(', $node->getStartSourceLocation());
                $this->emit($node->getFn());
                $this->emitStr(')', $node->getStartSourceLocation());
            }

            // Args
            $this->emitStr('(', $node->getStartSourceLocation());
            $argCount = count($node->getArguments());
            foreach ($node->getArguments() as $i => $arg) {
                if ($i < $argCount - 1) {
                    $this->emit($arg);
                    $this->emitStr(', ', $node->getStartSourceLocation());
                } else {
                    $this->emitStr('...((', $node->getStartSourceLocation());
                    $this->emit($arg);
                    $this->emitStr(') ?? [])', $node->getStartSourceLocation());
                }
            }
            $this->emitStr(')', $node->getStartSourceLocation());
        }

        $this->emitContextSuffix($node->getEnv(), $node->getStartSourceLocation());
    }

    protected function emitIf(IfNode $node) {
        if ($node->getEnv()->getContext() == NodeEnvironment::CTX_EXPR) {
            $this->emitStr('(\Phel\Lang\Truthy::isTruthy(', $node->getStartSourceLocation());
            $this->emit($node->getTestExpr());
            $this->emitStr(')) ? ', $node->getStartSourceLocation());
            $this->emit($node->getThenExpr());
            $this->emitStr(' : ', $node->getStartSourceLocation());
            $this->emit($node->getElseExpr());
        } else {
            $this->emitStr('if (\Phel\Lang\Truthy::isTruthy(', $node->getStartSourceLocation());
            $this->emit($node->getTestExpr());
            $this->emitLine(')) {', $node->getStartSourceLocation());
            $this->indentLevel++;
            $this->emit($node->getThenExpr());
            $this->indentLevel--;
            $this->emitLine();
            $this->emitLine('} else {', $node->getStartSourceLocation());
            $this->indentLevel++;
            $this->emit($node->getElseExpr());
            $this->indentLevel--;
            $this->emitLine();
            $this->emitLine('}', $node->getStartSourceLocation());
        }
    }

    protected function emitCall(CallNode $node) {
        $this->emitContextPrefix($node->getEnv(), $node->getStartSourceLocation());

        $fnNode = $node->getFn();
        if ($fnNode instanceof PhpVarNode && $fnNode->isInfix()) {
            // Args
            $this->emitStr('(', $node->getStartSourceLocation());
            $this->emitArgList($node->getArguments(), $node->getStartSourceLocation(), ' ' . $fnNode->getName() . ' ');
            $this->emitStr(')', $node->getStartSourceLocation());
        } else {
            if ($fnNode instanceof PhpVarNode) {
                $this->emitStr($fnNode->getName(), $fnNode->getStartSourceLocation());
            } else {
                $this->emitStr('(', $node->getStartSourceLocation());
                $this->emit($node->getFn());
                $this->emitStr(')', $node->getStartSourceLocation());
            }

            // Args
            $this->emitStr('(', $node->getStartSourceLocation());
            $this->emitArgList($node->getArguments(), $node->getStartSourceLocation());
            $this->emitStr(')', $node->getStartSourceLocation());
        }

        $this->emitContextSuffix($node->getEnv(), $node->getStartSourceLocation());
    }

    protected function emitArgList(array $nodes, ?SourceLocation $sepLoc, string $sep = ', ') {
        $nodesCount = count($nodes);
        foreach ($nodes as $i => $arg) {
            $this->emit($arg);

            if ($i < $nodesCount - 1) {
                $this->emitStr($sep, $sepLoc);
            }
        }
    }

    protected function emitLocalVar(LocalVarNode $node) {
        $this->emitContextPrefix($node->getEnv(), $node->getStartSourceLocation());
        $this->emitPhpVariable($node->getName());
        $this->emitContextSuffix($node->getEnv(), $node->getStartSourceLocation());
    }

    protected function emitGlobalVar(GlobalVarNode $node) {
        $this->emitContextPrefix($node->getEnv(), $node->getStartSourceLocation());
        $this->emitGlobalBase($node->getNamespace(), $node->getName());
        $this->emitContextSuffix($node->getEnv(), $node->getStartSourceLocation());
    }

    protected function emitLet(LetNode $node) {
        $wrapFn = $node->getEnv()->getContext() == NodeEnvironment::CTX_EXPR;

        if ($wrapFn) {
            $this->emitFnWrapPrefix($node->getEnv(), $node->getStartSourceLocation());
        }

        foreach ($node->getBindings() as $binding) {
            $this->emitPhpVariable($binding->getShadow(), $binding->getStartSourceLocation());
            $this->emitStr(' = ', $node->getStartSourceLocation());
            $this->emit($binding->getInitExpr());
            $this->emitLine(';', $node->getStartSourceLocation());
        }

        if ($node->isLoop()) {
            $this->emitLine('while (true) {', $node->getStartSourceLocation());
            $this->indentLevel++;
        }

        $this->emit($node->getBodyExpr());

        if ($node->isLoop()) {
            $this->emitLine('break;', $node->getStartSourceLocation());
            $this->indentLevel--;
            $this->emitStr('}', $node->getStartSourceLocation());
        }

        if ($wrapFn) {
            $this->emitFnWrapSuffix($node->getEnv(), $node->getStartSourceLocation());
        }
    }

    protected function emitDo(DoNode $node) {
        $wrapFn = count($node->getStmts()) > 0 && $node->getEnv()->getContext() == NodeEnvironment::CTX_EXPR;
        if ($wrapFn) {
            $this->emitFnWrapPrefix($node->getEnv(), $node->getStartSourceLocation());
        }

        foreach ($node->getStmts() as $i => $stmt) {
            $this->emit($stmt);
            $this->emitLine();
        }
        $this->emit($node->getRet());

        if ($wrapFn) {
            $this->emitFnWrapSuffix($node->getEnv(), $node->getStartSourceLocation());
        }
    }

    protected function emitQuote(QuoteNode $node) {
        $this->emitContextPrefix($node->getEnv(), $node->getStartSourceLocation());
        $this->emitPhel($node->getValue());
        $this->emitContextSuffix($node->getEnv(), $node->getStartSourceLocation());
    }

    protected function emitFnAsClass(FnNode $node) {
        $this->emitContextPrefix($node->getEnv(), $node->getStartSourceLocation());

        $this->emitStr('new class(', $node->getStartSourceLocation());

        $usesCount = count($node->getUses());
        foreach ($node->getUses() as $i => $u) {
            $loc = $u->getStartLocation();
            $shadowed = $node->getEnv()->getShadowed($u);
            if ($shadowed) {
                $u = $shadowed;
            }

            $this->emitPhpVariable($u, $loc);

            if ($i < $usesCount - 1) {
                $this->emitStr(', ', $node->getStartSourceLocation());
            }
        }

        $this->emitLine(') extends \Phel\Lang\AFn {', $node->getStartSourceLocation());
        $this->indentLevel++;

        $this->emitLine('public const BOUND_TO = "' . addslashes($node->getEnv()->getBoundTo()) . '";', $node->getStartSourceLocation());


        foreach ($node->getUses() as $i => $u) {
            $shadowed = $node->getEnv()->getShadowed($u);
            if ($shadowed) {
                $u = $shadowed;
            }

            $this->emitLine('private $'. $this->munge($u->getName()). ';', $node->getStartSourceLocation());
        }

        // Constructor
        if ($usesCount) {
            $this->emitLine();
            $this->emitStr('public function __construct(', $node->getStartSourceLocation());
            
            // Constructor parameter
            foreach ($node->getUses() as $i => $u) {
                $shadowed = $node->getEnv()->getShadowed($u);
                if ($shadowed) {
                    $u = $shadowed;
                }
    
                $this->emitPhpVariable($u, $node->getStartSourceLocation());
    
                if ($i < $usesCount - 1) {
                    $this->emitStr(', ', $node->getStartSourceLocation());
                }
            }

            $this->emitLine(') {', $node->getStartSourceLocation());
            $this->indentLevel++;

            // Constructor assignment
            foreach ($node->getUses() as $i => $u) {
                $shadowed = $node->getEnv()->getShadowed($u);
                if ($shadowed) {
                    $u = $shadowed;
                }
    
                $varName = $this->munge($u->getName());
                $this->emitLine('$this->' . $varName . ' = $' . $varName . ';', $node->getStartSourceLocation());
            }

            $this->indentLevel--;
            $this->emitLine('}', $node->getStartSourceLocation());
        }

        // __invoke Function
        $this->emitLine();
        $this->emitStr('public function __invoke(', $node->getStartSourceLocation());

        // Function Parameters
        $paramsCount = count($node->getParams());
        foreach ($node->getParams() as $i => $p) {
            if ($i == $paramsCount - 1 && $node->isVariadic()) {
                $this->emitPhpVariable($p, null, false, true);
            } else {
                $meta = $p->getMeta();
                $this->emitPhpVariable($p, null, $meta[new Keyword("reference")]);
            }

            if ($i < $paramsCount - 1) {
                $this->emitStr(', ', $node->getStartSourceLocation());
            }
        }

        $this->emitLine(') {', $node->getStartSourceLocation());
        $this->indentLevel++;

        // Use Parameter extraction
        foreach ($node->getUses() as $i => $u) {
            $shadowed = $node->getEnv()->getShadowed($u);
            if ($shadowed) {
                $u = $shadowed;
            }

            $varName = $this->munge($u->getName());
            $this->emitLine('$' . $varName . ' = $this->' . $varName . ';', $node->getStartSourceLocation());
        }

        // Variadic Parameter
        if ($node->isVariadic()) {
            $p = $node->getParams()[count($node->getParams()) - 1];
            $this->emitLine('$' . $this->munge($p->getName()) . ' = new \Phel\Lang\PhelArray($' . $this->munge($p->getName()) . ');', $node->getStartSourceLocation());
        }

        // Body
        if ($node->getRecurs()) {
            $this->emitLine('while (true) {', $node->getStartSourceLocation());
            $this->indentLevel++;
        }
        $this->emit($node->getBody());
        if ($node->getRecurs()) {
            $this->emitLine('break;', $node->getStartSourceLocation());
            $this->indentLevel--;
            $this->emitStr('}', $node->getStartSourceLocation());
        }

        // End of __invoke
        $this->indentLevel--;
        $this->emitLine();
        $this->emitLine('}', $node->getStartSourceLocation());

        // End of class
        $this->indentLevel--;
        $this->emitStr('}', $node->getStartSourceLocation());

        $this->emitContextSuffix($node->getEnv(), $node->getStartSourceLocation());
    }

    protected function emitDef(DefNode $node) {
        $this->emitGlobalBase($node->getNamespace(), $node->getName());
        $this->emitStr(" = ", $node->getStartSourceLocation());
        $this->emit($node->getInit());
        $this->emitLine(";", $node->getStartSourceLocation());

        if (count($node->getMeta()) > 0) {
            $this->emitGlobalBaseMeta($node->getNamespace(), $node->getName());
            $this->emitStr(" = ", $node->getStartSourceLocation());
            $this->emitPhel($node->getMeta());
            $this->emitLine(";", $node->getStartSourceLocation());
        }
    }

    protected function emitLiteral(LiteralNode $node) {
        if (!($node->getEnv()->getContext() == NodeEnvironment::CTX_STMT)) {
            $this->emitContextPrefix($node->getEnv(), $node->getStartSourceLocation());
            $this->emitPhel($node->getValue());
            $this->emitContextSuffix($node->getEnv(), $node->getStartSourceLocation());
        }
    }

    protected function emitTable(TableNode $node) {
        $this->emitContextPrefix($node->getEnv(), $node->getStartSourceLocation());
        $this->emitStr('\Phel\Lang\Table::fromKVs(', $node->getStartSourceLocation());
        $this->emitArgList($node->getKeyValues(), $node->getStartSourceLocation());
        $this->emitStr(')', $node->getStartSourceLocation());
        $this->emitContextSuffix($node->getEnv(), $node->getStartSourceLocation());
    }

    protected function emitArray(ArrayNode $node) {
        $this->emitContextPrefix($node->getEnv(), $node->getStartSourceLocation());
        $this->emitStr('\Phel\Lang\PhelArray::create(', $node->getStartSourceLocation());
        $this->emitArgList($node->getValues(), $node->getStartSourceLocation());
        $this->emitStr(')', $node->getStartSourceLocation());
        $this->emitContextSuffix($node->getEnv(), $node->getStartSourceLocation());
    }

    /**
     * Emits a Phel value.
     * 
     * @param Phel|scalar|null $x The value
     */
    protected function emitPhel($x): void {
        if (is_float($x)) {
            $this->emitStr($this->printFloat($x));
        } else if (is_int($x)) {
            $this->emitStr((string) $x);
        } else if (is_string($x)) {
            $p = new Printer();
            $this->emitStr($p->printString($x, true));
        } else if ($x === null) {
            $this->emitStr('null');
        } else if (is_bool($x)) {
            $this->emitStr($x == true ? 'true' : 'false');
        } else if ($x instanceof Keyword) {
            $this->emitStr('new \Phel\Lang\Keyword("' . addslashes($x->getName()) . '")', $x->getStartLocation());
        } else if ($x instanceof Symbol) {
            $this->emitStr('(new \Phel\Lang\Symbol("' . addslashes($x->getName()) . '"))', $x->getStartLocation());
        } else if ($x instanceof PhelArray) {
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
        } else if ($x instanceof Table) {
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
        } else if ($x instanceof Tuple) {
            if ($x->isUsingBracket()) {
                $this->emitStr('\Phel\Lang\Tuple::createBracket(', $x->getStartLocation());
            } else {
                $this->emitStr('\Phel\Lang\Tuple::create(', $x->getStartLocation());
            };

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

    private function emitGlobalBase(string $namespace, Symbol $name) {
        $this->emitStr(
            '$GLOBALS["__phel"]["' . addslashes($namespace) . '"]["' . addslashes($name->getName()) . '"]',
            $name->getStartLocation()
        );
    }

    private function emitGlobalBaseMeta(string $namespace, Symbol $name) {
        $this->emitStr(
            '$GLOBALS["__phel_meta"]["' . addslashes($namespace) . '"]["' . addslashes($name->getName()) . '"]',
            $name->getStartLocation()
        );
    }

    private function emitContextPrefix(NodeEnvironment $env, ?SourceLocation $sl = null) {
        if ($env->getContext() == NodeEnvironment::CTX_RET) {
            $this->emitStr('return ', $sl);
        }
    }

    private function emitContextSuffix(NodeEnvironment $env, ?SourceLocation $sl = null) {
        if ($env->getContext() != NodeEnvironment::CTX_EXPR) {
            $this->emitStr(';', $sl);
        }
    }

    private function emitFnWrapPrefix(NodeEnvironment $env, ?SourceLocation $sl = null) {
        $this->emitStr('(function()', $sl);
        if (count($env->getLocals()) > 0) {
            $this->emitStr(' use(', $sl);

            foreach ($env->getLocals() as $i => $l) {
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

    private function emitFnWrapSuffix(NodeEnvironment $env, ?SourceLocation $sl = null) {
        $this->indentLevel--;
        $this->emitLine();
        $this->emitStr("})()", $sl);
    }

    private function printFloat(float $x): string {
        if (intval($x) == $x) {
            // (string) 10.0 will return 10 and not 10.0
            // so we just add a .0 at the end
            return ((string) $x) . '.0';
        } else {
            return ((string) $x);
        }
    }

    protected function munge(string $s): string {
        return Munge::encode($s);
    }

    protected function emitLine($str = '', ?SourceLocation $sl = null) {
        if (strlen($str) > 0) {
            $this->emitStr($str, $sl);
        }

        $this->generatedLines++;
        $this->generatedColumns = 0;

        echo PHP_EOL;
    }

    protected function emitStr($str, ?SourceLocation $sl = null) {
        if ($this->generatedColumns == 0) {
            $this->generatedColumns += $this->indentLevel * 2;
            echo str_repeat(' ', $this->indentLevel * 2);
        }

        if ($this->enableSourceMaps && $sl) {
            $this->sourceMap[] = [
                'source' => $sl->getFile(),
                'original' => [
                    'line' => $sl->getLine() - 1,
                    'column' => $sl->getColumn()
                ],
                'generated' => [
                    'line' => $this->generatedLines,
                    'column' => $this->generatedColumns
                ],
            ];
        }

        $this->generatedColumns += strlen($str);

        echo $str;
    }
}