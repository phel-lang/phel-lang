<?php

namespace Phel;

use Exception;
use ParseError;
use Phel\Ast\ApplyNode;
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
use Phel\Ast\ThrowNode;
use Phel\Ast\TryNode;
use Phel\Ast\TupleNode;
use Phel\Lang\Keyword;
use Phel\Lang\Phel;
use Phel\Lang\PhelArray;
use Phel\Lang\Symbol;
use Phel\Lang\Table;
use Phel\Lang\Tuple;
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

    public function emitAndEval(Node $node): string {
        $code = $this->emit($node);
        // echo $code . "\n";
        $filename = tempnam(sys_get_temp_dir(), '__phel');
        if ($filename) {
            file_put_contents($filename, "<?php \n" . $code);
            try {
                require $filename;
            } catch (Throwable $e) {
                throw $e;
            }
        } else {
            // TODO: Improve exception.
            throw new Exception("can not create temp file.");
        }
        
        
        return $code;
    }

    public function emit(Node $node): string {
        if ($node instanceof NsNode) {
            return $this->emitNs($node);
        } else if ($node instanceof DefNode) {
            return $this->emitDef($node);
        } else if ($node instanceof LiteralNode) {
            return $this->emitLiteral($node);
        } else if ($node instanceof QuoteNode) {
            return $this->emitQuote($node);
        } else if ($node instanceof FnNode) {
            return $this->emitFnAsClass($node);
        } else if ($node instanceof DoNode) {
            return $this->emitDo($node);
        } else if ($node instanceof LetNode) {
            return $this->emitLet($node);
        } else if ($node instanceof LocalVarNode) {
            return $this->emitLocalVar($node);
        } else if ($node instanceof GlobalVarNode) {
            return $this->emitGlobalVar($node);
        } else if ($node instanceof CallNode) {
            return $this->emitCall($node);
        } else if ($node instanceof IfNode) {
            return $this->emitIf($node);
        } else if ($node instanceof ApplyNode) {
            return $this->emitApply($node);
        } else if ($node instanceof TupleNode) {
            return $this->emitTuple($node);
        } else if ($node instanceof PhpNewNode) {
            return $this->emitPhpNew($node);
        } else if ($node instanceof PhpVarNode) {
            return $this->emitPhpVar($node);
        } else if ($node instanceof PhpObjectCallNode) {
            return $this->emitObjectCall($node);
        } else if ($node instanceof RecurNode) {
            return $this->emitRecur($node);
        } else if ($node instanceof ThrowNode) {
            return $this->emitThrow($node);
        } else if ($node instanceof TryNode) {
            return $this->emitTry($node);
        } else if ($node instanceof CatchNode) {
            return $this->emitCatch($node);
        } else if ($node instanceof PhpArrayGetNode) {
            return $this->emitPhpArrayGet($node);
        } else if ($node instanceof PhpArraySetNode) {
            return $this->emitPhpArraySet($node);
        } else if ($node instanceof PhpArrayUnsetNode) {
            return $this->emitPhpArrayUnset($node);
        } else if ($node instanceof PhpClassNameNode) {
            return $this->emitClassName($node);
        } else if ($node instanceof PhpArrayPushNode) {
            return $this->emitPhpArrayPush($node);
        } else if ($node instanceof ForeachNode) {
            return $this->emitForeach($node);
        } else {
            throw new \Exception('Unexpected node: ' . get_class($node));
        }
    }

    protected function emitForeach(ForeachNode $node): string {
        $keyStr = $node->getKeySymbol() ? '$' . $this->munge($node->getKeySymbol()->getName()) . ' => ' : '';
        $valueStr = '$' . $this->munge($node->getValueSymbol()->getName());
        $code = (
            'foreach (' . $this->emit($node->getListExpr()) . ' as ' . $keyStr . $valueStr . ') {'
            . "\n"
            . $this->indent($this->emit($node->getBodyExpr()), 1)
            . "\n"
            . '}'
        );

        if ($node->getEnv()->getContext() == NodeEnvironment::CTX_STMT) {
            return $code;
        } else {
            return $this->wrap($this->wrapFn((
                $code
                . "\n"
                . 'return null;'
            ), $node->getEnv()), $node->getEnv());
        }
    }

    protected function emitClassName(PhpClassNameNode $node): string {
        return $node->getName()->getName();
    }

    protected function emitPhpArrayUnset(PhpArrayUnsetNode $node): string {
        return $this->wrap(
            'unset((' . $this->emit($node->getArrayExpr()) . ')[('.$this->emit($node->getAccessExpr()).')])',
            $node->getEnv()
        );
    }

    protected function emitPhpArrayGet(PhpArrayGetNode $node): string {
        return $this->wrap(
            '(' . $this->emit($node->getArrayExpr()) . ')[('.$this->emit($node->getAccessExpr()).')] ?? null',
            $node->getEnv()
        );
    }

    protected function emitPhpArraySet(PhpArraySetNode $node): string {
        return $this->wrap(
            '(' . $this->emit($node->getArrayExpr()) . ')[('.$this->emit($node->getAccessExpr()).')] = ' . $this->emit($node->getValueExpr()),
            $node->getEnv()
        );
    }

    protected function emitPhpArrayPush(PhpArrayPushNode $node): string {
        return $this->wrap(
            '(' . $this->emit($node->getArrayExpr()) . ')[] = ' . $this->emit($node->getValueExpr()),
            $node->getEnv()
        );
    }

    protected function emitTry(TryNode $node): string {
        if ($node->getFinally() || count($node->getCatches()) > 0) {
            $bodyCode = (
                'try {' . "\n" 
                . $this->indent($this->emit($node->getBody()), 1)
                . "\n"
                . '}'
            );

            $catchCodes = [];
            foreach ($node->getCatches() as $catchNode) {
                $catchCodes[] = $this->emit($catchNode);
            }

            $finallyCode = '';
            if ($node->getFinally()) {
                $finallyCode = (
                    ' finally {' . "\n" 
                    . $this->indent($this->emit($node->getFinally()), 1)
                    . "\n"
                    . "}"
                );
            }

            $code = $bodyCode . implode('', $catchCodes) . $finallyCode;
            if ($node->getEnv()->getContext() == NodeEnvironment::CTX_EXPR) {
                return $this->wrapFn($code, $node->getEnv());
            }  else {
                return $code;
            }
        } else {
            return $this->emit($node->getBody());
        }
    }

    protected function emitCatch(CatchNode $node): string {
        return (
            ' catch (' . $node->getType()->getName() . ' $' . $this->munge($node->getName()->getName()) . ') {' . "\n"
            . $this->indent($this->emit($node->getBody()), 1)
            . "\n"
            . '}'
        );
    }

    protected function emitThrow(ThrowNode $node): string {
        $code = "throw " . $this->emit($node->getExceptionExpr()) . ";";

        if ($node->getEnv()->getContext() == NodeEnvironment::CTX_EXPR) {
            return $this->wrapFn($code, $node->getEnv());
        } else {
            return $code;
        }
    }

    protected function emitRecur(RecurNode $node): string {
        $params = $node->getFrame()->getParams();
        $exprs = $node->getExprs();
        $env = $node->getEnv();

        $tempCode = [];
        $setCode = [];
        foreach ($exprs as $i => $expr) {
            $tempSym = Symbol::gen();
            $paramSym = $params[$i];
            $shadowedSym = $env->getShadowed($paramSym);
            if ($shadowedSym) {
                $paramSym = $shadowedSym;
            }
            $tempCode[] = '$' . $tempSym->getName() . ' = ' . $this->emit($expr) . ';';
            $setCode[] = '$' . $this->munge($paramSym->getName()) . ' = ' . '$' . $tempSym->getName() . ';';
        }

        return (
            implode("\n", $tempCode)
            . ((count($tempCode) > 0) ? "\n" : '')
            . implode("\n", $setCode)
            . ((count($setCode) > 0) ? "\n" : '')
            . "continue;\n"
        );
    }

    protected function emitNs(NsNode $node): string {
        $requireNs = $node->getRequireNs();
        if (count($requireNs) > 0) {
            return implode("\n", array_map(function(Symbol $ns): string {
                return '\Phel\Runtime::getInstance()->loadNs("' . \addslashes($ns->getName()) . '");';
            }, $requireNs));
        } else {
            return '';
        }
    }

    protected function emitObjectCall(PhpObjectCallNode $node): string {
        $fnCode = $node->isStatic() ? '::' : '->';
        $targetExpr = $node->getTargetExpr();
        $callExpr = $node->getCallExpr();

        $parts = [];

        if ($callExpr instanceof MethodCallNode) {
            $args = [];
            foreach ($callExpr->getArgs() as $arg) {
                $args[] = $this->emit($arg);
            }

            $callCode = $callExpr->getFn()->getName() . '(' . implode(', ', $args) . ')';
        } else if ($callExpr instanceof PropertyOrConstantAccessNode) {
            $callCode = $callExpr->getName()->getName();
        } else {
            throw new \Exception('Not supported ' . get_class($callExpr));
        }

        if ($node->isStatic() && $targetExpr instanceof PhpClassNameNode) {
            $targetCode = $targetExpr->getName()->getName();
        } else {
            $targetSym = Symbol::gen('target_');
            $parts[] = '$' . $targetSym->getName() . ' = ' . $this->emit($targetExpr) . ';';
            $targetCode = '$' . $targetSym->getName();
        }

        if (count($parts) > 0) {
            return $this->wrap($this->wrapFn(
                implode("\n", $parts) . "\n" 
                . 'return ' . $targetCode . $fnCode . $callCode . ';',
                $node->getEnv()
            ), $node->getEnv());
        } else {
            return $this->wrap('(' . $targetCode . $fnCode . $callCode . ')', $node->getEnv());
        }
    }

    protected function emitPhpVar(PhpVarNode $node): string {
        if ($node->isCallable()) {
            return $this->wrap('(function(...$args) { return ' . $node->getName() . '(...$args);' . '})', $node->getEnv());
        } else {
            return $this->wrap($node->getName(), $node->getEnv());
        }
    }

    protected function emitPhpNew(PhpNewNode $node): string {
        $args = [];
        foreach ($node->getArgs() as $arg) {
            $args[] = $this->emit($arg);
        }

        $classExpr = $node->getClassExpr();

        if ($classExpr instanceof PhpClassNameNode) {
            return $this->wrap(
                '(new ' . $classExpr->getName()->getName() . '(' . implode(', ', $args) . '))', 
                $node->getEnv()
            );
        } else {
            $targetSym = Symbol::gen('target_');
            

            $code = (
                '$' . $targetSym->getName() . ' = ' . $this->emit($classExpr) . ";\n"
                . 'new $' . $targetSym->getName() . '(' . implode(', ', $args) . ');'
            );

            return $this->wrap($this->wrapFn($code, $node->getEnv()), $node->getEnv());
        }
    }

    protected function emitTuple(TupleNode $node): string {
        $args = [];
        foreach ($node->getArgs() as $arg) {
            $args[] = $this->emit($arg);
        }

        return $this->wrap('\Phel\Lang\Tuple::createBracket(' . implode(',', $args) . ')', $node->getEnv());
    }

    protected function emitApply(ApplyNode $node): string {
        $args = [];
        foreach ($node->getArguments() as $arg) {
            $args[] = $this->emit($arg);
        }

        $lastArg = array_pop($args);

        $fnNode = $node->getFn();
        if ($fnNode instanceof PhpVarNode) {
            if ($fnNode->isInfix()) {
                return $this->wrap(
                    'array_reduce('
                    . 'array_merge([' . implode(', ', $args) . '], [...(' . $lastArg . ' ?? [])]), ' 
                    . 'function($a, $b) { return ($a ' . $fnNode->getName() . ' $b); }'
                    . ')',
                    $node->getEnv()
                );
            } else {
                if (count($args) > 0) {
                    $argString = implode(', ', $args) . ', ...(' . $lastArg . ' ?? [])';
                } else {
                    $argString = '...(' . $lastArg . ' ?? [])';
                }
                
                return $this->wrap(
                    $fnNode->getName() . '(' . $argString .')',
                    $node->getEnv()
                );
            }
        } else {
            if (count($args) > 0) {
                $argString = implode(', ', $args) . ', ...(' . $lastArg. ' ?? [])';
            } else {
                $argString = '...(' . $lastArg . ' ?? [])';
            }

            return $this->wrap(
                '(' . $this->emit($node->getFn()) . ')' . '(' . $argString . ')',
                $node->getEnv()
            );
        }
    }

    protected function emitIf(IfNode $node): string {
        if ($node->getEnv()->getContext() == NodeEnvironment::CTX_EXPR) {
            return (
                '(\Phel\Lang\Truthy::isTruthy(' . $this->emit($node->getTestExpr()) . '))'  // TODO: Make sure test expr evals to true or false
                . ' ? ' . $this->emit($node->getThenExpr())
                . ' : ' . $this->emit($node->getElseExpr())
            );
        } else {
            return (
                'if (\Phel\Lang\Truthy::isTruthy(' . $this->emit($node->getTestExpr()) . ')) {' . "\n" // TODO: Make sure test expr evals to true or false
                . $this->indent($this->emit($node->getThenExpr()), 1)
                . "\n"
                . '} else {' . "\n"
                . $this->indent($this->emit($node->getElseExpr()), 1)
                . "\n"
                . '}' . "\n"
            );
        }
    }

    protected function emitCall(CallNode $node): string {
        $args = [];
        foreach ($node->getArguments() as $arg) {
            $args[] = $this->emit($arg);
        }

        $fnNode = $node->getFn();
        if ($fnNode instanceof PhpVarNode) {
            if ($fnNode->isInfix()) {
                return $this->wrap(
                    '('
                    . implode(' ' . $fnNode->getName() . ' ', $args)
                    .')',
                    $node->getEnv()
                );
            } else {
                return $this->wrap(
                    $fnNode->getName() . '('
                    . implode(', ', $args)
                    .')',
                    $node->getEnv()
                );
            }
        } else {
            return $this->wrap(
                '(' . $this->emit($node->getFn()) . ')' . '(' . implode(', ', $args) . ')',
                $node->getEnv()
            );
        }
    }

    protected function emitLocalVar(LocalVarNode $node): string {
        return $this->wrap('$' . $this->munge($node->getName()->getName()), $node->getEnv());
    }

    protected function emitGlobalVar(GlobalVarNode $node): string {
        return $this->wrap($this->emitGlobalBase($node->getNamespace(), $node->getName()) . '->get()', $node->getEnv());
    }

    protected function emitLet(LetNode $node): string {
        $parts = [];
        foreach ($node->getBindings() as $binding) {
            $parts[] = (
                '$' . $this->munge($binding->getShadow()->getName())
                . ' = '
                . $this->emit($binding->getInitExpr())
                . ';'
            );
        }

        $body = $this->emit($node->getBodyExpr());
        if ($node->isLoop()) {
            $body = $this->wrapRecur($body . "\n");
        }
        $parts[] = $body;

        $code = implode("\n", $parts);

        if ($node->getEnv()->getContext() == NodeEnvironment::CTX_EXPR) {
            return $this->wrapFn($code, $node->getEnv());
        } else {
            return $code;
        }
    }

    protected function emitDo(DoNode $node): string {
        $parts = [];

        foreach ($node->getStmts() as $stmt) {
            $parts[] = $this->emit($stmt);
        }
        $parts[] = $this->emit($node->getRet());

        $code = trim(implode("\n", $parts));
        
        if (count($node->getStmts()) > 0 && $node->getEnv()->getContext() == NodeEnvironment::CTX_EXPR) {
            return $this->wrapFn($code, $node->getEnv());
        } else {
            return $code;
        }
    }

    protected function emitQuote(QuoteNode $node): string {
        return $this->wrap($this->emitPhel($node->getValue()), $node->getEnv());
    }

    protected function emitFnAsClass(FnNode $node) {
        $params = [];
        $variadicWrapString = '';
        foreach ($node->getParams() as $i => $p) {
            if ($i == count($node->getParams()) - 1 && $node->isVariadic()) {
                $params[] = '...$' . $this->munge($p->getName());
                $variadicWrapString = '$' . $this->munge($p->getName()) . ' = new \Phel\Lang\PhelArray($' . $this->munge($p->getName()) . ');' . "\n";
            } else {
                $params[] = '$' . $this->munge($p->getName());
            }
        }
        $paramString = implode(', ', $params);

        $constructorParameter = [];
        $constructorSetter = [];
        $invokeGetter = [];
        $properties = [];
        foreach ($node->getUses() as $u) {
            $shadowed = $node->getEnv()->getShadowed($u);
            if ($shadowed) {
                $u = $shadowed;
            }

            $varName = $this->munge($u->getName());

            $constructorParameter[] = '$' . $varName;
            $constructorSetter[] = '$this->' . $varName . ' = $' . $varName . ';';
            $properties[] = 'private $' . $varName . ';';
            $invokeGetter[] = '$' . $varName . ' = $this->' . $varName . ';';
        }

        $constructorParameterString = implode(', ', $constructorParameter);
        $constructorSetterString = implode("\n", $constructorSetter);

        $constructor = (
            "public function __construct($constructorParameterString) {\n"
            . $this->indent($constructorSetterString, 1)
            . (empty($constructorParameter) ? '' : "\n")
            . "}\n"
        );

        $invokeGetterString = implode("\n", $invokeGetter);

        $body = $this->emit($node->getBody());

        if ($node->getRecurs()) {
            $body = $this->wrapRecur($body);
        }

        $invokeFn = (
            "public function __invoke($paramString) {\n"
            . $this->indent($invokeGetterString, 1)
            . (empty($invokeGetter) ? '' : "\n")
            . ($variadicWrapString ? $this->indent($variadicWrapString, 1) : '')
            . $this->indent($body, 1)
            . "}\n"
        );

        $constString = (
            'public const BOUND_TO = "' . addslashes($node->getEnv()->getBoundTo()) . '";'
        );
        $propertiesString = implode("\n", $properties);

        return $this->wrap(
            "new class($constructorParameterString) implements \Phel\Lang\IFn {\n"
            . $this->indent($constString, 1)
            . "\n"
            . $this->indent($propertiesString, 1)
            . "\n"
            . $this->indent($constructor, 1)
            . "\n"
            . $this->indent($invokeFn, 1)
            . "\n"
            . '}',
            $node->getEnv()
        );
    }

    protected function emitFn(FnNode $node): string {
        $params = [];
        $variadicWrapString = '';
        foreach ($node->getParams() as $i => $p) {
            if ($i == count($node->getParams()) - 1 && $node->isVariadic()) {
                $params[] = '...$' . $this->munge($p->getName());
                $variadicWrapString = '$' . $this->munge($p->getName()) . ' = new \Phel\Lang\PhelArray($' . $this->munge($p->getName()) . ');' . "\n";
            } else {
                $params[] = '$' . $this->munge($p->getName());
            }
        }
        $paramString = implode(', ', $params);

        $uses = [];
        foreach ($node->getUses() as $u) {
            $shadowed = $node->getEnv()->getShadowed($u);
            if ($shadowed) {
                $u = $shadowed;
            }

            $uses[] = '$' . $this->munge($u->getName());
        }

        $useString = count($uses) > 0
            ? ' use (' . implode(', ', $uses) . ')'
            : '';

        $body = $this->emit($node->getBody());

        if ($node->getRecurs()) {
            $body = $this->wrapRecur($body);
        }

        return $this->wrap(
            'function(' . $paramString .')' . $useString . " {\n"
            . ($variadicWrapString ? $this->indent($variadicWrapString, 1) : '')
            . $this->indent($body, 1)
            . "\n"
            . '}',
            $node->getEnv()
        );
    }

    protected function emitDef(DefNode $node): string {
        return (
            $this->emitGlobalBase($node->getNamespace(), $node->getName())
            . " = new \Phel\Lang\PhelVar(\n"
            . $this->indent($this->emit($node->getInit()) . ",", 1)
            . "\n"
            . $this->indent($this->emitPhel($node->getMeta()), 1)
            . "\n"
            . ");\n"
        );
    }

    protected function emitLiteral(LiteralNode $node): string {
        if ($node->getEnv()->getContext() == NodeEnvironment::CTX_STMT) {
            return '';
        } else {
            $v = $node->getValue();
            return $this->wrap($this->emitPhel($v), $node->getEnv());
        }
    }

    /**
     * Emits a Phel value.
     * 
     * @param Phel|scalar|null $x The value
     * 
     * @return string
     */
    protected function emitPhel($x): string {
        if (is_float($x)) {
            return $this->printFloat($x);
        } else if (is_int($x)) {
            return (string) $x;
        } else if (is_string($x)) {
            $p = new Printer();
            return $p->printString($x, true);
        } else if ($x === null) {
            return 'null';
        } else if (is_bool($x)) {
            return $x == true ? 'true' : 'false';
        } else if ($x instanceof Keyword) {
            return 'new \Phel\Lang\Keyword("' . addslashes($x->getName()) . '")';
        } else if ($x instanceof Symbol) {
            return '(new \Phel\Lang\Symbol("' . addslashes($x->getName()) . '"))';
        } else if ($x instanceof PhelArray) {
            $values = [];
            foreach ($x as $v) {
                $values[] = $this->emitPhel($v);
            }
            if (count($values) > 1) {
                $valuesStr = "\n" . $this->indent(implode(",\n", $values), 1);
            } else {
                $valuesStr = implode(",", $values);
            }
            return '\Phel\Lang\PhelArray::create('.$valuesStr.')';
        } else if ($x instanceof Table) {
            $values = [];
            foreach ($x as $key => $value) {
                $values[] = $this->emitPhel($key) . ", " . $this->emitPhel($value);
            }
            if (count($values) > 0) {
                $valuesStr = "\n" . $this->indent(implode(",\n", $values), 1) . "\n";
            } else {
                $valuesStr = '';
            }
            return '\Phel\Lang\Table::fromKVs(' . $valuesStr . ')';
        } else if ($x instanceof Tuple) {
            $values = [];
            foreach ($x as $v) {
                $values[] = $this->emitPhel($v);
            }
            if (count($values) > 1) {
                $valuesStr = "\n" .  $this->indent(implode(",\n", $values), 1);
            } else {
                $valuesStr = implode(",", $values);
            }

            if ($x->isUsingBracket()) {
                return '\Phel\Lang\Tuple::createBracket(' . $valuesStr . ')';
            } else {
                return '\Phel\Lang\Tuple::create(' . $valuesStr . ')';
            };
        } else {
            throw new \Exception('literal not supported: ' . gettype($x));
        }
    }

    private function emitGlobalBase(string $namespace, Symbol $name): string {
        return '$GLOBALS["__phel"]["' . addslashes($namespace) . '"]["' . addslashes($name->getName()) . '"]';
    }

    private function indent(string $text, int $i): string {
        if ($text == '' && strpos($text, "\n") === false) {
            return $text;
        } else {
            $lines = explode("\n", $text);
            $indentString = str_repeat('  ', $i);
            $f = function(string $line) use($indentString): string { return ($line == '') ? $line : $indentString . $line; };

            return implode("\n", array_map($f, $lines));
        }
    }

    private function wrap(string $code, NodeEnvironment $env): string {
        return (
            ($env->getContext() == NodeEnvironment::CTX_RET ? 'return ' : '')
            . $code
            . ($env->getContext() != NodeEnvironment::CTX_EXPR ? ';' : '')
        );
    }

    private function wrapFn(string $code, NodeEnvironment $env): string {
        $uses = [];
        foreach ($env->getLocals() as $l) {
            $shadowed = $env->getShadowed($l);
            if ($shadowed) {
                $uses[] = '$' . $this->munge($shadowed->getName());
            } else {
                $uses[] = '$' . $this->munge($l->getName());
            }
        }

        $useString = count($uses) > 0
            ? ' use (' . implode(', ', $uses) . ')'
            : '';

        return (
            "(function()$useString {\n"
            . $this->indent($code, 1)
            . "\n"
            . "})()"
        );
    }

    private function wrapRecur(string $code): string {
        return (
            'while(true) {' . "\n"
            . $this->indent($code, 1)
            . $this->indent("break;", 1)
            . "\n"
            . "}"
        );
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
}