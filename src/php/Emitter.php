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
use Phel\SourceMap\SourceMap;
use Throwable;

class Emitter {

    /**
     * @var array
     */
    protected $mungeMapping = [
        '-' => '_',
        '.' => '_DOT_',
        ':' => '_COLON_',
        '+' => '_PLUS_',
        '>' => '_GT_',
        '<' => '_LT_',
        '=' => '_EQ_',
        '~' => '_TILDE_',
        '!' => '_BANG_',
        '@' => '_CIRCA_',
        '#' => "_SHARP_",
        '\'' => "_SINGLEQUOTE_",
        '"' => "_DOUBLEQUOTE_",
        '%' => "_PERCENT_",
        '^' => "_CARET_",
        '&' => "_AMPERSAND_",
        '*' => "_STAR_",
        '|' => "_BAR_",
        '{' => "_LBRACE_",
        '}' => "_RBRACE_",
        '[' => "_LBRACK_",
        ']' => "_RBRACK_",
        '/' => "_SLASH_",
        '\\' => "_BSLASH_",
        '?' => "_QMARK_"
    ];

    private $indentLevel = 0;
    private $generatedLines = 0;
    private $generatedColumns = 0;
    private $sourceMap = [];

    public function emitAndEval(Node $node): array {
        $this->generatedLines = 0;
        $this->generatedColumns = 0;
        $this->indentLevel = 0;
        $this->sourceMap = [];

        ob_start();
        $this->emit($node);
        $code = ob_get_contents();
        ob_end_clean();

        $sm = new SourceMap();
        $sourceMap = $sm->encode($this->sourceMap);

        $filename = tempnam(sys_get_temp_dir(), '__phel');
        if ($filename) {
            $fileContent = (
                "<?php\n"
                . '// ;;' . $sourceMap . "\n"
                . $code
            );

            file_put_contents($filename, $fileContent);
            try {
                require $filename;
            } catch (Throwable $e) {
                throw $e;
            }
        } else {
            // TODO: Improve exception.
            throw new Exception("can not create temp file.");
        }
        
        return [$code, $sourceMap];
    }

    public function emit(Node $node) {
        if ($node instanceof NsNode) {
            $this->emitNs($node);
        } else if ($node instanceof DefNode) {
            $this->emitDef($node);
        } else if ($node instanceof LiteralNode) {
            $this->emitLiteral($node);
        } else if ($node instanceof QuoteNode) {
            $this->emitQuote($node);
        } else if ($node instanceof FnNode) {
            $this->emitFnAsClass($node);
        } else if ($node instanceof DoNode) {
            $this->emitDo($node);
        } else if ($node instanceof LetNode) {
            $this->emitLet($node);
        } else if ($node instanceof LocalVarNode) {
            $this->emitLocalVar($node);
        } else if ($node instanceof GlobalVarNode) {
            $this->emitGlobalVar($node);
        } else if ($node instanceof CallNode) {
            $this->emitCall($node);
        } else if ($node instanceof IfNode) {
            $this->emitIf($node);
        } else if ($node instanceof ApplyNode) {
            $this->emitApply($node);
        } else if ($node instanceof TupleNode) {
            $this->emitTuple($node);
        } else if ($node instanceof PhpNewNode) {
            $this->emitPhpNew($node);
        } else if ($node instanceof PhpVarNode) {
            $this->emitPhpVar($node);
        } else if ($node instanceof PhpObjectCallNode) {
            $this->emitObjectCall($node);
        } else if ($node instanceof RecurNode) {
            $this->emitRecur($node);
        } else if ($node instanceof ThrowNode) {
            $this->emitThrow($node);
        } else if ($node instanceof TryNode) {
            $this->emitTry($node);
        } else if ($node instanceof CatchNode) {
            $this->emitCatch($node);
        } else if ($node instanceof PhpArrayGetNode) {
            $this->emitPhpArrayGet($node);
        } else if ($node instanceof PhpArraySetNode) {
            $this->emitPhpArraySet($node);
        } else if ($node instanceof PhpArrayUnsetNode) {
            $this->emitPhpArrayUnset($node);
        } else if ($node instanceof PhpClassNameNode) {
            $this->emitClassName($node);
        } else if ($node instanceof PhpArrayPushNode) {
            $this->emitPhpArrayPush($node);
        } else if ($node instanceof ForeachNode) {
            $this->emitForeach($node);
        } else if ($node instanceof ArrayNode) {
            $this->emitArray($node);
        } else if ($node instanceof TableNode) {
            $this->emitTable($node);
        } else {
            throw new \Exception('Unexpected node: ' . get_class($node));
        }
    }

    protected function emitForeach(ForeachNode $node) {
        if ($node->getEnv()->getContext() !== NodeEnvironment::CTX_STMT) {
            $this->emitContextPrefix($node->getEnv(), $node->getStartSourceLocation());
            $this->emitFnWrapPrefix($node->getEnv(), $node->getStartSourceLocation());
        }

        $this->emitStr('foreach (', $node->getStartSourceLocation());
        $this->emit($node->getListExpr());
        $this->emitStr(' as ', $node->getStartSourceLocation());
        if ($node->getKeySymbol()) {
            $this->emitStr('$' . $this->munge($node->getKeySymbol()->getName()), $node->getKeySymbol()->getStartLocation());
            $this->emitStr(' => ', $node->getStartSourceLocation());
        }
        $this->emitStr('$' . $this->munge($node->getValueSymbol()->getName()), $node->getValueSymbol()->getStartLocation());
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

            $this->emitStr('$' . $tempSym->getName() . ' = ', $node->getStartSourceLocation());
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

            $this->emitStr('$' . $this->munge($paramSym->getName()), $loc);
            $this->emitLine(' = $' . $tempSym->getName() . ';', $node->getStartSourceLocation());
        }

        $this->emitLine('continue;', $node->getStartSourceLocation());
    }

    protected function emitNs(NsNode $node) {
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
            $this->emitStr('$' . $targetSym->getName() . ' = ', $node->getStartSourceLocation());
            $this->emit($targetExpr);
            $this->emitLine(';', $node->getStartSourceLocation());

            $this->emitStr('return ', $node->getStartSourceLocation());
            $this->emitStr('$' . $targetSym->getName(), $node->getStartSourceLocation());
            $this->emitStr($fnCode, $node->getStartSourceLocation());
        }

        // Method/Property and Arguments
        if ($callExpr instanceof MethodCallNode) {
            $this->emitStr($callExpr->getFn()->getName(), $callExpr->getFn()->getStartLocation());
            $this->emitStr('(', $node->getStartSourceLocation());
            foreach ($callExpr->getArgs() as $i => $arg) {
                $this->emit($arg);

                if ($i < count($callExpr->getArgs()) - 1) {
                    $this->emitStr(', ', $node->getStartSourceLocation());
                }
            }
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
            $this->emitStr('$' . $targetSym->getName() . ' = ', $node->getStartSourceLocation());
            $this->emit($classExpr);
            $this->emitLine(';', $node->getStartSourceLocation());

            $this->emitStr('new $' . $targetSym->getName() . '(', $node->getStartSourceLocation());
        }

        // Args
        foreach ($node->getArgs() as $i => $arg) {
            $this->emit($arg);

            if ($i < count($node->getArgs()) - 1) {
                $this->emitStr(', ', $node->getStartSourceLocation());
            }
        }

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

        foreach ($node->getArgs() as $i => $value) {
            $this->emit($value);

            if ($i < count($node->getArgs()) - 1) {
                $this->emitStr(', ', $node->getStartSourceLocation());
            }
        }

        $this->emitStr(')', $node->getStartSourceLocation());
        $this->emitContextSuffix($node->getEnv(), $node->getStartSourceLocation());
    }

    protected function emitApply(ApplyNode $node) {
        $this->emitContextPrefix($node->getEnv(), $node->getStartSourceLocation());

        $fnNode = $node->getFn();

        if ($fnNode instanceof PhpVarNode && $fnNode->isInfix()) {
            $this->emitStr('array_reduce([', $node->getStartSourceLocation());
            // Args
            foreach ($node->getArguments() as $i => $arg) {
                if ($i < count($node->getArguments()) - 1) {
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
            foreach ($node->getArguments() as $i => $arg) {
                if ($i < count($node->getArguments()) - 1) {
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
            $this->emitStr('(', $node->getStartSourceLocation());

            // Args
            foreach ($node->getArguments() as $i => $arg) {
                $this->emit($arg);

                if ($i < count($node->getArguments()) - 1) {
                    $this->emitStr(' ' . $fnNode->getName() . ' ', $fnNode->getStartSourceLocation());
                }
            }

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
            foreach ($node->getArguments() as $i => $arg) {
                $this->emit($arg);

                if ($i < count($node->getArguments()) - 1) {
                    $this->emitStr(', ', $node->getStartSourceLocation());
                }
            }
            $this->emitStr(')', $node->getStartSourceLocation());
        }

        $this->emitContextSuffix($node->getEnv(), $node->getStartSourceLocation());
    }

    protected function emitLocalVar(LocalVarNode $node) {
        $this->emitContextPrefix($node->getEnv(), $node->getStartSourceLocation());
        $this->emitStr('$' . $this->munge($node->getName()->getName()),  $node->getName()->getStartLocation());
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
            $this->emitStr('$' . $this->munge($binding->getShadow()->getName()), $binding->getStartSourceLocation());
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

        foreach ($node->getUses() as $i => $u) {
            $loc = $u->getStartLocation();
            $shadowed = $node->getEnv()->getShadowed($u);
            if ($shadowed) {
                $u = $shadowed;
            }

            $this->emitStr('$'. $this->munge($u->getName()), $node->getStartSourceLocation());

            if ($i < count($node->getUses()) - 1) {
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
        if (count($node->getUses())) {
            $this->emitLine();
            $this->emitStr('public function __construct(', $node->getStartSourceLocation());
            
            // Constructor parameter
            foreach ($node->getUses() as $i => $u) {
                $shadowed = $node->getEnv()->getShadowed($u);
                if ($shadowed) {
                    $u = $shadowed;
                }
    
                $this->emitStr('$'. $this->munge($u->getName()), $node->getStartSourceLocation());
    
                if ($i < count($node->getUses()) - 1) {
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
        foreach ($node->getParams() as $i => $p) {
            if ($i == count($node->getParams()) - 1 && $node->isVariadic()) {
                $this->emitStr('...$' . $this->munge($p->getName()), $p->getStartLocation());
            } else {
                $meta = $p->getMeta();
                if ($meta[new Keyword("reference")]) {
                    $prefix = '&';
                } else {
                    $prefix = '';
                }

                $this->emitStr($prefix . '$' . $this->munge($p->getName()), $p->getStartLocation());
            }

            if ($i < count($node->getParams()) - 1) {
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

        foreach ($node->getKeyValues() as $i => $keyOrValue) {
            $this->emit($keyOrValue);

            if ($i < count($node->getKeyValues()) - 1) {
                $this->emitStr(', ', $node->getStartSourceLocation());
            }
        }

        $this->emitStr(')', $node->getStartSourceLocation());
        $this->emitContextSuffix($node->getEnv(), $node->getStartSourceLocation());
    }

    protected function emitArray(ArrayNode $node) {
        $this->emitContextPrefix($node->getEnv(), $node->getStartSourceLocation());
        $this->emitStr('\Phel\Lang\PhelArray::create(', $node->getStartSourceLocation());

        foreach ($node->getValues() as $i => $value) {
            $this->emit($value);

            if ($i < count($node->getValues()) - 1) {
                $this->emitStr(', ', $node->getStartSourceLocation());
            }
        }

        $this->emitStr(')', $node->getStartSourceLocation());
        $this->emitContextSuffix($node->getEnv(), $node->getStartSourceLocation());
    }

    /**
     * Emits a Phel value.
     * 
     * @param Phel|scalar|null $x The value
     * 
     * @return string
     */
    protected function emitPhel($x) {
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
                    $this->emitStr('$' . $this->munge($shadowed->getName()), $sl);
                } else {
                    $this->emitStr('$' . $this->munge($l->getName()), $sl);
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
        if ($s == 'this') {
            return '__phel_this';
        } else {
            return str_replace(
                array_keys($this->mungeMapping),
                array_values($this->mungeMapping),
                $s
            );
        }
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

        if ($sl) {
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