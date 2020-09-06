<?php

declare(strict_types=1);

namespace Phel\Analyzer\TupleSymbol\ReadModel;

use Phel\Exceptions\AnalyzerException;
use Phel\Lang\Symbol;
use Phel\Lang\Tuple;

final class FnSymbolTuple
{
    private const STATE_START = 'start';
    private const STATE_REST = 'rest';
    private const STATE_DONE = 'done';

    public Tuple $parentTuple;

    public array $params = [];
    public array $lets = [];
    public bool $isVariadic = false;
    public bool $hasVariadicForm = false;
    public string $buildParamsState = self::STATE_START;


    public function __construct(Tuple $parentTuple)
    {
        $this->parentTuple = $parentTuple;
    }

    public function addDummyVariadicSymbol(): void
    {
        if ($this->isVariadic && !$this->hasVariadicForm) {
            $this->params[] = Symbol::gen();
        }
    }

    public function checkAllVariablesStartWithALetterOrUnderscore(Tuple $tuple): void
    {
        foreach ($this->params as $param) {
            if (!preg_match("/^[a-zA-Z_\x80-\xff].*$/", $param->getName())) {
                throw AnalyzerException::withLocation(
                    "Variable names must start with a letter or underscore: {$param->getName()}",
                    $tuple
                );
            }
        }
    }

    /** @param mixed $param */
    public function buildParamsStart($param): void
    {
        if ($param instanceof Symbol) {
            if ($this->isSymWithName($param, '&')) {
                $this->isVariadic = true;
                $this->buildParamsState = self::STATE_REST;
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
    }

    /** @param mixed $x */
    private function isSymWithName($x, string $name): bool
    {
        return $x instanceof Symbol && $x->getName() === $name;
    }

    /** @param mixed $param */
    public function buildParamsRest(Tuple $tuple, $param): void
    {
        $this->buildParamsState = self::STATE_DONE;
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
    }

    public function buildParamsDone(Tuple $tuple): void
    {
        throw AnalyzerException::withLocation(
            'Unsupported parameter form, only one symbol can follow the & parameter',
            $tuple
        );
    }
}
