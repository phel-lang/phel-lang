<?php

declare(strict_types=1);

namespace Phel\Analyzer\TupleSymbol;

use Phel\Analyzer\WithAnalyzer;
use Phel\Ast\FnNode;
use Phel\Ast\Node;
use Phel\Exceptions\AnalyzerException;
use Phel\Lang\Symbol;
use Phel\Lang\Tuple;
use Phel\NodeEnvironment;
use Phel\RecurFrame;

final class FnSymbol implements TupleSymbolAnalyzer
{
    use WithAnalyzer;

    private array $params = [];
    private array $lets = [];
    private bool $isVariadic = false;
    private bool $hasVariadicForm = false;
    private string $state = 'start';

    public function analyze(Tuple $tuple, NodeEnvironment $env): FnNode
    {
        $this->verifyArguments($tuple);
        $this->buildParams($tuple);
        $this->addDummyVariadicSymbol();
        $this->checkAllVariablesStartWithALetterOrUnderscore($tuple);

        $recurFrame = new RecurFrame($this->params);

        return new FnNode(
            $env,
            $this->params,
            $this->analyzeBody($tuple, $recurFrame, $env),
            $this->buildUsesFromEnv($env),
            $this->isVariadic,
            $recurFrame->isActive(),
            $tuple->getStartLocation()
        );
    }

    private function verifyArguments(Tuple $tuple): void
    {
        if (count($tuple) < 2) {
            throw AnalyzerException::withLocation("'fn requires at least one argument", $tuple);
        }

        if (!($tuple[1] instanceof Tuple)) {
            throw AnalyzerException::withLocation("Second argument of 'fn must be a Tuple", $tuple);
        }
    }

    private function buildParams(Tuple $tuple): void
    {
        /** @var Tuple $xs */
        $xs = $tuple[1];

        foreach ($xs as $param) {
            switch ($this->state) {
                case 'start':
                    if ($param instanceof Symbol) {
                        if ($this->isSymWithName($param, '&')) {
                            $this->isVariadic = true;
                            $this->state = 'rest';
                        } elseif ($param->getName() === '_') {
                            $this->params[] = Symbol::gen()->copyLocationFrom($param);
                        } else {
                            $this->params[] = $param;
                        }
                    } else {
                        $tempSym = Symbol::gen()->copyLocationFrom($param);
                        $this->params[] = $tempSym;
                        $this->lets[] = $param;
                        $this->lets[] = $tempSym;
                    }
                    break;
                case 'rest':
                    $this->state = 'done';
                    $this->hasVariadicForm = true;
                    if ($this->isSymWithName($param, '_')) {
                        $this->params[] = Symbol::gen()->copyLocationFrom($param);
                    } elseif ($param instanceof Symbol) {
                        $this->params[] = $param;
                    } else {
                        $tempSym = Symbol::gen()->copyLocationFrom($tuple);
                        $this->params[] = $tempSym;
                        $this->lets[] = $param;
                        $this->lets[] = $tempSym;
                    }
                    break;
                case 'done':
                    throw AnalyzerException::withLocation(
                        'Unsupported parameter form, only one symbol can follow the & parameter',
                        $tuple
                    );
            }
        }
    }

    /** @param mixed $x */
    private function isSymWithName($x, string $name): bool
    {
        return $x instanceof Symbol && $x->getName() === $name;
    }

    private function addDummyVariadicSymbol(): void
    {
        if ($this->isVariadic && !$this->hasVariadicForm) {
            $this->params[] = Symbol::gen();
        }
    }

    private function checkAllVariablesStartWithALetterOrUnderscore(Tuple $tuple): void
    {
        foreach ($this->params as $param) {
            if (!(preg_match("/^[a-zA-Z_\x80-\xff].*$/", $param->getName()))) {
                throw AnalyzerException::withLocation(
                    "Variable names must start with a letter or underscore: {$param->getName()}",
                    $tuple
                );
            }
        }
    }

    private function analyzeBody(Tuple $tuple, RecurFrame $recurFrame, NodeEnvironment $env): Node
    {
        $tupleBody = array_slice($tuple->toArray(), 2);

        $body = empty($this->lets)
            ? $this->createDoTupleWithBody($tupleBody)
            : $this->createLetTupleWithBody($tupleBody);

        $bodyEnv = $env
            ->withMergedLocals($this->params)
            ->withContext(NodeEnvironment::CTX_RET)
            ->withAddedRecurFrame($recurFrame);

        return $this->analyzer->analyze($body, $bodyEnv);
    }

    private function createDoTupleWithBody(array $body): Tuple
    {
        return Tuple::create(
            (Symbol::create(Symbol::NAME_DO))->copyLocationFrom($body),
            ...$body
        )->copyLocationFrom($body);
    }

    private function createLetTupleWithBody(array $tupleBody): Tuple
    {
        return Tuple::create(
            (Symbol::create(Symbol::NAME_LET))->copyLocationFrom($tupleBody),
            (new Tuple($this->lets, true))->copyLocationFrom($tupleBody),
            ...$tupleBody
        )->copyLocationFrom($tupleBody);
    }

    private function buildUsesFromEnv(NodeEnvironment $env): array
    {
        return array_diff($env->getLocals(), $this->params);
    }
}
