<?php

declare(strict_types=1);

namespace Phel\Analyzer\AnalyzeTuple;

use Phel\Analyzer\WithAnalyzer;
use Phel\Ast\FnNode;
use Phel\Exceptions\AnalyzerException;
use Phel\Lang\Symbol;
use Phel\Lang\Tuple;
use Phel\NodeEnvironment;
use Phel\RecurFrame;

final class AnalyzeFn
{
    use WithAnalyzer;

    public function __invoke(Tuple $x, NodeEnvironment $env): FnNode
    {
        $tupleCount = count($x);
        if ($tupleCount < 2) {
            throw new AnalyzerException(
                "'fn requires at least one argument",
                $x->getStartLocation(),
                $x->getEndLocation()
            );
        }

        if (!($x[1] instanceof Tuple)) {
            throw new AnalyzerException(
                "Second argument of 'fn must be a Tuple",
                $x->getStartLocation(),
                $x->getEndLocation()
            );
        }

        $params = [];
        $lets = [];
        $isVariadic = false;
        $hasVariadicForm = false;
        $state = 'start';
        $xs = $x[1];
        foreach ($xs as $param) {
            switch ($state) {
                case 'start':
                    if ($param instanceof Symbol) {
                        if ($this->isSymWithName($param, '&')) {
                            $isVariadic = true;
                            $state = 'rest';
                        } elseif ($param->getName() === '_') {
                            $params[] = Symbol::gen()->copyLocationFrom($param);
                        } else {
                            $params[] = $param;
                        }
                    } else {
                        $tempSym = Symbol::gen()->copyLocationFrom($param);
                        $params[] = $tempSym;
                        $lets[] = $param;
                        $lets[] = $tempSym;
                    }
                    break;
                case 'rest':
                    $state = 'done';
                    $hasVariadicForm = true;
                    if ($this->isSymWithName($param, '_')) {
                        $params[] = Symbol::gen()->copyLocationFrom($param);
                    } elseif ($param instanceof Symbol) {
                        $params[] = $param;
                    } else {
                        $tempSym = Symbol::gen()->copyLocationFrom($x);
                        $params[] = $tempSym;
                        $lets[] = $param;
                        $lets[] = $tempSym;
                    }
                    break;
                case 'done':
                    throw new AnalyzerException(
                        'Unsupported parameter form, only one symbol can follow the & parameter',
                        $x->getStartLocation(),
                        $x->getEndLocation()
                    );
            }
        }

        // Add a dummy variadic symbol
        if ($isVariadic && !$hasVariadicForm) {
            $params[] = Symbol::gen();
        }

        foreach ($params as $param) {
            if (!(preg_match("/^[a-zA-Z_\x80-\xff].*$/", $param->getName()))) {
                throw new AnalyzerException(
                    "Variable names must start with a letter or underscore: {$param->getName()}",
                    $x->getStartLocation(),
                    $x->getEndLocation()
                );
            }
        }

        $recurFrame = new RecurFrame($params);

        $body = array_slice($x->toArray(), 2);
        if (count($lets) > 0) {
            $body = Tuple::create(
                (new Symbol('let'))->copyLocationFrom($body),
                (new Tuple($lets, true))->copyLocationFrom($body),
                ...$body
            )->copyLocationFrom($body);
        } else {
            $body = Tuple::create(
                (new Symbol('do'))->copyLocationFrom($body),
                ...$body
            )->copyLocationFrom($body);
        }

        $bodyEnv = $env
            ->withMergedLocals($params)
            ->withContext(NodeEnvironment::CTX_RET)
            ->withAddedRecurFrame($recurFrame);

        $body = $this->analyzer->analyze($body, $bodyEnv);

        $uses = array_diff($env->getLocals(), $params);

        return new FnNode(
            $env,
            $params,
            $body,
            $uses,
            $isVariadic,
            $recurFrame->isActive(),
            $x->getStartLocation()
        );
    }

    /** @param mixed $x */
    private function isSymWithName($x, string $name): bool
    {
        return $x instanceof Symbol && $x->getName() === $name;
    }
}
