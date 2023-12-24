<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\ReadModel;

use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\Symbol;

use function array_slice;

final class FnSymbolTuple
{
    private const STATE_START = 'start';

    private const STATE_REST = 'rest';

    private const STATE_DONE = 'done';

    private const PARENT_TUPLE_BODY_OFFSET = 2;

    private array $params = [];

    private array $lets = [];

    private bool $isVariadic = false;

    private bool $hasVariadicForm = false;

    private string $buildParamsState = self::STATE_START;

    private function __construct(
        private readonly PersistentListInterface $parentList,
    ) {
    }

    public static function createWithTuple(PersistentListInterface $list): self
    {
        /** @var PersistentVectorInterface $params */
        $params = $list->get(1);
        $self = new self($list);

        foreach ($params->getIterator() as $param) {
            $self->buildParamsByState($param);
        }

        $self->addDummyVariadicSymbol();
        $self->checkAllVariablesStartWithALetterOrUnderscore();

        return $self;
    }

    public function params(): array
    {
        return $this->params;
    }

    public function lets(): array
    {
        return $this->lets;
    }

    public function isVariadic(): bool
    {
        return $this->isVariadic;
    }

    public function parentListBody(): array
    {
        return array_slice(
            $this->parentList->toArray(),
            self::PARENT_TUPLE_BODY_OFFSET,
        );
    }

    private function addDummyVariadicSymbol(): void
    {
        if (!$this->isVariadic) {
            return;
        }

        if ($this->hasVariadicForm) {
            return;
        }

        $this->params[] = Symbol::gen();
    }

    private function checkAllVariablesStartWithALetterOrUnderscore(): void
    {
        foreach ($this->params as $param) {
            if (preg_match("/^[a-zA-Z_\x80-\xff].*$/", (string) $param->getName()) === 0 || preg_match("/^[a-zA-Z_\x80-\xff].*$/", (string) $param->getName()) === 0 || preg_match("/^[a-zA-Z_\x80-\xff].*$/", (string) $param->getName()) === false) {
                throw AnalyzerException::withLocation(
                    'Variable names must start with a letter or underscore: ' . $param->getName(),
                    $this->parentList,
                );
            }
        }
    }

    private function buildParamsByState(mixed $param): void
    {
        switch ($this->buildParamsState) {
            case self::STATE_START:
                $this->buildParamsStart($param);
                break;
            case self::STATE_REST:
                $this->buildParamsRest($param);
                break;
            case self::STATE_DONE:
                $this->buildParamsDone();
        }
    }

    private function buildParamsStart(mixed $param): void
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

    private function isSymWithName(mixed $x, string $name): bool
    {
        return $x instanceof Symbol && $x->getName() === $name;
    }

    private function buildParamsRest(mixed $param): void
    {
        $this->buildParamsState = self::STATE_DONE;
        $this->hasVariadicForm = true;

        if ($this->isSymWithName($param, '_')) {
            $this->params[] = Symbol::gen()->copyLocationFrom($param);
        } elseif ($param instanceof Symbol) {
            $this->params[] = $param;
        } else {
            $tempSym = Symbol::gen()->copyLocationFrom($this->parentList);
            $this->params[] = $tempSym;
            $this->lets[] = $param;
            $this->lets[] = $tempSym;
        }
    }

    private function buildParamsDone(): never
    {
        throw AnalyzerException::withLocation(
            'Unsupported parameter form, only one symbol can follow the & parameter',
            $this->parentList,
        );
    }
}
